<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inbound\Api;

use App\Identity\Domain\Role;
use App\Inbound\Domain\AsnId;
use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\Event\PutawayConfirmed;
use App\Inbound\Domain\Event\ReceiptClosed;
use App\Inbound\Domain\LocationId;
use App\Inbound\Domain\OperatorId;
use App\Inbound\Domain\ProductId;
use App\Inbound\Domain\PutawayTaskId;
use App\Inbound\Domain\ReceiptId;
use App\Inbound\Domain\ReceivedGoods;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\Ids;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * From a closed receipt to stock in its locations: the stock is booked at the dock, the putaway is
 * planned with the configured strategy (by family), and confirming a task moves the stock.
 */
final class PutawayApiTest extends InboundApiTestCase
{
    private string $fashionShelfId;

    private string $quarantineId;

    private string $chilledId;

    private string $tinyId;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->request('POST', '/api/products/'.$this->shirtId.'/classification', ['family' => 'CAMISERIA', 'turnoverClass' => 'A']);

        $fashion = $this->zone('MODA', 'ambient', 1, ['CAMISERIA']);
        $this->fashionShelfId = $this->location($this->warehouseId, $fashion, 'C-01-01', 'reserve');
        $this->quarantineId = $this->location($this->warehouseId, $this->zone('CUAR', 'ambient', 2), 'Q-01-01', 'quarantine');
        $this->chilledId = $this->location($this->warehouseId, $this->zone('FRIO', 'chilled', 3), 'F-01-01', 'reserve');
        $this->tinyId = $this->location($this->warehouseId, $this->inboundZoneId, 'T-01-01', 'reserve', ['maxWeightKg' => 1]);
    }

    public function testClosingAReceiptBooksTheStockAtTheDockAndPlansThePutaway(): void
    {
        $receipt = $this->closedReceipt();

        self::assertSame(40, $this->unitsAt($this->dockId, $this->shirtId));
        self::assertSame(10, $this->unitsAt($this->dockId, $this->trousersId), 'Damaged units are at the dock too, in quarantine.');
        self::assertSame(2, $this->quarantinedAt($this->dockId), 'INB-10');

        $tasks = $this->request('GET', '/api/putaway-tasks', null, null, ['receiptId' => $this->id($receipt)]);
        $this->assertStatus(200);
        self::assertSame([40, 8, 2], array_column($tasks, 'units'), 'INB-14: the tasks hold what the receipt brought.');
        self::assertSame([$this->shirtId, $this->trousersId, $this->trousersId], array_column($tasks, 'productId'));
        self::assertSame([false, false, true], array_column($tasks, 'quarantine'));
        self::assertSame(['pending', 'pending', 'pending'], array_column($tasks, 'status'));
        self::assertSame(
            [$this->fashionShelfId, $this->reserveId, $this->quarantineId],
            array_column($tasks, 'suggestedLocationId'),
            'Shirts go to their family, trousers to the first free location, damaged units to quarantine.',
        );
    }

    public function testAnOperatorConfirmsAPutawayAndTheStockMoves(): void
    {
        $task = $this->tasks($this->closedReceipt())[0];
        $this->role = Role::Operator;

        $confirmed = $this->request('POST', '/api/putaway-tasks/'.$this->id($task).'/confirm', ['locationId' => $this->fashionShelfId]);

        $this->assertStatus(200);
        self::assertSame('confirmed', $confirmed['status']);
        self::assertSame($this->fashionShelfId, $confirmed['confirmedLocationId']);
        self::assertNull($confirmed['overrideReason']);
        self::assertIsString($confirmed['confirmedAt']);
        self::assertTrue($confirmed['stockMoved'], 'The tests run the policies at once; a worker does it a moment later.');
        self::assertSame($confirmed, $this->request('GET', '/api/putaway-tasks/'.$this->id($task)));
        self::assertSame(0, $this->unitsAt($this->dockId, $this->shirtId));
        self::assertSame(40, $this->unitsAt($this->fashionShelfId, $this->shirtId));

        $again = $this->request('POST', '/api/putaway-tasks/'.$this->id($task).'/confirm', ['locationId' => $this->fashionShelfId]);
        $this->assertProblem(409, 'inbound.putaway_task_already_confirmed', $again);
    }

    // INB-13
    public function testAnotherLocationNeedsAReason(): void
    {
        $task = $this->tasks($this->closedReceipt())[0];

        $problem = $this->request('POST', '/api/putaway-tasks/'.$this->id($task).'/confirm', ['locationId' => $this->reserveId]);
        $this->assertProblem(422, 'inbound.putaway_override_reason_required', $problem);

        $confirmed = $this->request('POST', '/api/putaway-tasks/'.$this->id($task).'/confirm', ['locationId' => $this->reserveId, 'reason' => 'Estantería de moda en obras']);
        $this->assertStatus(200);
        self::assertSame('Estantería de moda en obras', $confirmed['overrideReason']);
        self::assertSame(40, $this->unitsAt($this->reserveId, $this->shirtId));
    }

    // INB-11
    public function testRefusesALocationThatCannotTakeTheGoods(): void
    {
        $task = $this->tasks($this->closedReceipt())[0];
        $reason = 'Prueba';
        $this->request('POST', '/api/locations/'.$this->reserveId.'/block', ['reason' => 'Rack dañado']);

        foreach ([
            'unknown' => Ids::next(),
            'blocked' => $this->reserveId,
            'wrong_type' => $this->quarantineId,
            'storage_class' => $this->chilledId,
            'no_room' => $this->tinyId,
            'same_as_source' => $this->dockId,
        ] as $expected => $locationId) {
            $problem = $this->request('POST', '/api/putaway-tasks/'.$this->id($task).'/confirm', ['locationId' => $locationId, 'reason' => $reason]);

            $this->assertProblem(422, 'inbound.putaway_destination_invalid', $problem);
            self::assertSame($expected, $this->parameter($problem, 'reason'));
        }

        self::assertSame(40, $this->unitsAt($this->dockId, $this->shirtId), 'Nothing moved.');
    }

    // INB-12, INB-14
    public function testSplitsATaskToPutPartOfItElsewhere(): void
    {
        $task = $this->tasks($this->closedReceipt())[0];
        $this->role = Role::Operator;

        $new = $this->request('POST', '/api/putaway-tasks/'.$this->id($task).'/split', ['units' => 15]);
        $this->assertStatus(201);
        self::assertSame(15, $new['units']);
        self::assertSame(25, $this->request('GET', '/api/putaway-tasks/'.$this->id($task))['units']);

        $this->request('POST', '/api/putaway-tasks/'.$this->id($task).'/confirm', ['locationId' => $this->fashionShelfId]);
        $this->request('POST', '/api/putaway-tasks/'.$this->id($new).'/confirm', ['locationId' => $this->reserveId, 'reason' => 'No cabe todo']);

        self::assertSame(25, $this->unitsAt($this->fashionShelfId, $this->shirtId));
        self::assertSame(15, $this->unitsAt($this->reserveId, $this->shirtId));
        self::assertSame(0, $this->unitsAt($this->dockId, $this->shirtId));

        $problem = $this->request('POST', '/api/putaway-tasks/'.$this->id($task).'/split', ['units' => 1]);
        $this->assertProblem(409, 'inbound.putaway_task_already_confirmed', $problem);
    }

    // INB-12
    public function testASplitLeavesUnitsOnBothSides(): void
    {
        $task = $this->tasks($this->closedReceipt())[0];

        $problem = $this->request('POST', '/api/putaway-tasks/'.$this->id($task).'/split', ['units' => 40]);

        $this->assertProblem(422, 'inbound.putaway_split_invalid', $problem);
    }

    public function testListsThePendingWork(): void
    {
        $tasks = $this->tasks($this->closedReceipt());
        $this->request('POST', '/api/putaway-tasks/'.$this->id($tasks[0]).'/confirm', ['locationId' => $this->fashionShelfId]);

        $pending = $this->request('GET', '/api/putaway-tasks', null, null, ['status' => 'pending']);
        $confirmed = $this->request('GET', '/api/putaway-tasks', null, null, ['status' => 'confirmed']);

        self::assertCount(2, $pending);
        self::assertSame([$this->id($tasks[0])], array_column($confirmed, 'id'));

        $this->request('GET', '/api/putaway-tasks', null, null, ['status' => 'lost']);
        $this->assertStatus(400);
    }

    public function testHidesTheTaskOfAnotherTenant(): void
    {
        $task = $this->tasks($this->closedReceipt())[0];

        $problem = $this->request('GET', '/api/putaway-tasks/'.$this->id($task), null, Ids::next());

        $this->assertProblem(404, 'inbound.putaway_task_not_found', $problem);
    }

    // ADR 0012: a worker may get the same message twice. The second time changes nothing.
    public function testARedeliveredClosedReceiptBooksNothingTwice(): void
    {
        $receipt = $this->closedReceipt();
        self::assertTrue($receipt['stockBooked']);
        $asnId = $receipt['asnId'] ?? null;
        self::assertIsString($asnId);

        $this->events()->publish(new ReceiptClosed(ReceiptId::fromString($this->id($receipt)), AsnId::fromString($asnId), OperatorId::fromString(Ids::next()), new \DateTimeImmutable()));

        self::assertSame(40, $this->unitsAt($this->dockId, $this->shirtId));
        self::assertCount(3, $this->tasks($receipt));
    }

    // ADR 0012
    public function testARedeliveredPutawayMovesNothingTwice(): void
    {
        $task = $this->tasks($this->closedReceipt())[0];
        $this->request('POST', '/api/putaway-tasks/'.$this->id($task).'/confirm', ['locationId' => $this->fashionShelfId]);
        $receiptId = $task['receiptId'] ?? null;
        self::assertIsString($receiptId);
        $tenantId = TenantId::fromString($this->tenant);

        $this->events()->publish(new PutawayConfirmed(
            PutawayTaskId::fromString($this->id($task)),
            $tenantId,
            new ReceivedGoods(ProductId::fromString($this->shirtId), 40, null, null, false),
            LocationId::fromString($this->dockId),
            LocationId::fromString($this->fashionShelfId),
            AsnReference::fromString('ALB-2027-0153'),
            OperatorId::fromString(Ids::next()),
            new \DateTimeImmutable(),
        ));

        self::assertSame(0, $this->unitsAt($this->dockId, $this->shirtId), 'A second move would have found nothing to take.');
        self::assertSame(40, $this->unitsAt($this->fashionShelfId, $this->shirtId));
    }

    public function testDescribesTheApiInOpenApi(): void
    {
        $paths = $this->openApiPaths();

        foreach (['/api/putaway-tasks', '/api/putaway-tasks/{id}', '/api/putaway-tasks/{id}/confirm', '/api/putaway-tasks/{id}/split'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
    }

    /**
     * A closed receipt of 40 shirts and 10 trousers, two of them damaged.
     *
     * @return array<array-key, mixed>
     */
    private function closedReceipt(): array
    {
        $receipt = $this->openReceipt($this->registerAsn());
        $this->receive($receipt, self::SHIRT_GTIN, 40);
        $this->receive($receipt, self::TROUSERS_GTIN, 8);
        $this->receive($receipt, self::TROUSERS_GTIN, 2, ['condition' => 'damaged']);
        $closed = $this->request('POST', '/api/receipts/'.$this->id($receipt).'/close');
        $this->assertStatus(200);

        return $closed;
    }

    /**
     * @param array<array-key, mixed> $receipt
     *
     * @return list<array<array-key, mixed>>
     */
    private function tasks(array $receipt): array
    {
        $tasks = [];
        foreach ($this->request('GET', '/api/putaway-tasks', null, null, ['receiptId' => $this->id($receipt)]) as $task) {
            self::assertIsArray($task);
            $tasks[] = $task;
        }

        return $tasks;
    }

    /**
     * @param list<string> $families
     */
    private function zone(string $code, string $storageClass, int $pickSequence, array $families = []): string
    {
        $zone = $this->id($this->request('POST', '/api/warehouses/'.$this->warehouseId.'/zones', ['code' => $code, 'name' => $code, 'storageClass' => $storageClass, 'pickSequence' => $pickSequence]));
        if ([] !== $families) {
            $this->request('POST', '/api/zones/'.$zone.'/families', ['families' => $families]);
        }

        return $zone;
    }

    private function unitsAt(string $locationId, string $productId): int
    {
        $units = $this->connection()->fetchOne('SELECT COALESCE(SUM(on_hand), 0) FROM stock_items WHERE location_id = ? AND product_id = ?', [$locationId, $productId]);
        \assert(is_numeric($units));

        return (int) $units;
    }

    private function quarantinedAt(string $locationId): int
    {
        $units = $this->connection()->fetchOne("SELECT COALESCE(SUM(on_hand), 0) FROM stock_items WHERE location_id = ? AND status = 'quarantine'", [$locationId]);
        \assert(is_numeric($units));

        return (int) $units;
    }

    private function events(): EventBus
    {
        return self::getContainer()->get(EventBus::class);
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(EntityManagerInterface::class)->getConnection();
    }
}
