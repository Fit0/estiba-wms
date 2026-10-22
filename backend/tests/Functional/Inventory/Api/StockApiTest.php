<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory\Api;

use App\Identity\Domain\Role;
use App\Inventory\Application\RebuildStockOverview\RebuildStockOverview;
use App\Inventory\Domain\Event\StockMovementRecorded;
use App\Inventory\Domain\MovementType;
use App\Inventory\Domain\StockItemId;
use App\Inventory\Domain\StockMovementId;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\TenantId;
use App\Tests\Functional\Inbound\Api\InboundApiTestCase;
use App\Tests\Unit\Support\Ids;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The stock read model (ADR 0013), fed by a real receipt and putaway: in the tests the worker's
 * transport is synchronous, so the read model is up to date when the request answers.
 */
final class StockApiTest extends InboundApiTestCase
{
    public function testAClosedReceiptShowsItsStockAtTheDock(): void
    {
        $this->closedReceipt();

        $stock = $this->request('GET', '/api/stock-items', null, null, ['locationId' => $this->dockId]);

        $this->assertStatus(200);
        self::assertSame(['CAM-001', 'PAN-001', 'PAN-001'], array_column($stock, 'sku'));
        self::assertSame(['Producto CAM-001', 'Producto PAN-001', 'Producto PAN-001'], array_column($stock, 'productName'));
        self::assertSame(['available', 'available', 'quarantine'], array_column($stock, 'status'), 'INB-10: damaged units are in quarantine.');
        self::assertSame([40, 8, 2], array_column($stock, 'onHand'));
        self::assertSame(['DK-01-01', 'DK-01-01', 'DK-01-01'], array_column($stock, 'locationCode'));
        self::assertSame(['ZGZ1', 'ZGZ1', 'ZGZ1'], array_column($stock, 'warehouseCode'));
        self::assertSame([], array_column($stock, 'lot'), 'With no lot, the field is left out, like every null.');
    }

    public function testFiltersTheStockByProductWarehouseAndStatus(): void
    {
        $this->closedReceipt();

        $trousers = $this->request('GET', '/api/stock-items', null, null, ['productId' => $this->trousersId, 'warehouseId' => $this->warehouseId]);
        $quarantined = $this->request('GET', '/api/stock-items', null, null, ['status' => 'quarantine']);

        self::assertSame([8, 2], array_column($trousers, 'onHand'));
        self::assertSame([$this->trousersId], array_column($quarantined, 'productId'));
    }

    public function testKeepsLotsAndExpiryDatesApart(): void
    {
        $receipt = $this->openReceipt($this->registerAsn('ALB-2027-0200', [['productId' => $this->yoghurtId, 'units' => 30]]));
        $this->receive($receipt, self::YOGHURT_GTIN, 20, ['lot' => 'L-0425', 'expiryDate' => '2027-03-31']);
        $this->receive($receipt, self::YOGHURT_GTIN, 10, ['lot' => 'L-0426', 'expiryDate' => '2027-04-30']);
        $this->request('POST', '/api/receipts/'.$this->id($receipt).'/close');

        $stock = $this->request('GET', '/api/stock-items', null, null, ['productId' => $this->yoghurtId]);

        self::assertSame(['L-0425', 'L-0426'], array_column($stock, 'lot'));
        self::assertSame(['2027-03-31', '2027-04-30'], array_column($stock, 'expiryDate'));
        self::assertSame([20, 10], array_column($stock, 'onHand'));
    }

    public function testRefusesAnUnknownStatus(): void
    {
        $problem = $this->request('GET', '/api/stock-items', null, null, ['status' => 'lost']);

        $this->assertProblem(400, 'shared.request_malformed', $problem);
    }

    public function testAPutawayMovesTheStockToItsLocation(): void
    {
        $this->putAwayTheShirts();

        $shirts = $this->request('GET', '/api/stock-items', null, null, ['productId' => $this->shirtId]);

        self::assertSame(['A-01-01'], array_column($shirts, 'locationCode'), 'The dock is empty, so it is not listed.');
        self::assertSame([40], array_column($shirts, 'onHand'));
    }

    public function testShowsOneStockItem(): void
    {
        $this->closedReceipt();
        $listed = $this->request('GET', '/api/stock-items', null, null, ['productId' => $this->shirtId])[0];
        self::assertIsArray($listed);

        $stockItem = $this->request('GET', '/api/stock-items/'.$this->id($listed));

        $this->assertStatus(200);
        self::assertSame($listed, $stockItem);
    }

    public function testHidesTheStockOfAnotherTenant(): void
    {
        $this->closedReceipt();
        $listed = $this->request('GET', '/api/stock-items')[0];
        self::assertIsArray($listed);
        $stranger = Ids::next();

        self::assertSame([], $this->request('GET', '/api/stock-items', null, $stranger));
        $this->assertProblem(404, 'inventory.stock_item_not_found', $this->request('GET', '/api/stock-items/'.$this->id($listed), null, $stranger));
    }

    public function testShowsTheStockOfAProductByWarehouse(): void
    {
        $this->closedReceipt();

        $stock = $this->request('GET', '/api/products/'.$this->trousersId.'/stock');

        $this->assertStatus(200);
        self::assertSame('PAN-001', $stock['sku']);
        self::assertSame('Producto PAN-001', $stock['name']);
        self::assertSame([10, 8, 2, 0, 0], [$stock['onHand'], $stock['available'], $stock['quarantine'], $stock['damaged'], $stock['onHold']]);
        self::assertSame([[
            'warehouseId' => $this->warehouseId,
            'warehouseCode' => 'ZGZ1',
            'onHand' => 10,
            'available' => 8,
            'quarantine' => 2,
            'damaged' => 0,
            'onHold' => 0,
            'locations' => 1,
        ]], $stock['warehouses']);
    }

    public function testAProductWithoutStockHasNone(): void
    {
        $stock = $this->request('GET', '/api/products/'.$this->strangerId.'/stock');

        $this->assertStatus(200);
        self::assertSame(0, $stock['onHand']);
        self::assertSame([], $stock['warehouses']);
    }

    public function testAProductOutsideTheCatalogIsNotFound(): void
    {
        $problem = $this->request('GET', '/api/products/'.Ids::next().'/stock');

        $this->assertProblem(404, 'inventory.product_not_found', $problem);
    }

    public function testListsTheMovementsOfAProductNewestFirst(): void
    {
        $this->putAwayTheShirts();

        $movements = $this->request('GET', '/api/stock-movements', null, null, ['productId' => $this->shirtId]);

        $this->assertStatus(200);
        self::assertSame(['transfer_in', 'transfer_out', 'receipt'], array_column($movements, 'type'));
        self::assertSame([40, -40, 40], array_column($movements, 'change'));
        self::assertSame([40, 0, 40], array_column($movements, 'balanceAfter'));
        self::assertSame(['A-01-01', 'DK-01-01', 'DK-01-01'], array_column($movements, 'locationCode'));
        self::assertSame(['ALB-2027-0153', 'ALB-2027-0153', 'ALB-2027-0153'], array_column($movements, 'reference'), 'The ledger says where the stock came from.');
    }

    public function testListsTheMovementsOfOneStockItem(): void
    {
        $this->putAwayTheShirts();
        $shelf = $this->request('GET', '/api/stock-items', null, null, ['productId' => $this->shirtId])[0];
        self::assertIsArray($shelf);

        $movements = $this->request('GET', '/api/stock-movements', null, null, ['stockItemId' => $this->id($shelf)]);

        self::assertSame(['transfer_in'], array_column($movements, 'type'));
        self::assertSame([], $this->request('GET', '/api/stock-movements', null, Ids::next()), 'Another tenant sees none.');
    }

    public function testOperatorsMayReadTheStock(): void
    {
        $this->closedReceipt();
        $this->role = Role::Operator;

        $this->request('GET', '/api/stock-items');
        $this->assertStatus(200);
        $this->request('GET', '/api/stock-movements');
        $this->assertStatus(200);
        $this->request('GET', '/api/products/'.$this->shirtId.'/stock');
        $this->assertStatus(200);
    }

    // ADR 0013: the projection writes the current state, so a redelivered movement changes nothing.
    public function testARedeliveredMovementLeavesTheReadModelAsItWas(): void
    {
        $this->closedReceipt();
        $before = $this->request('GET', '/api/stock-items');
        $shirts = $before[0];
        self::assertIsArray($shirts);

        $this->events()->publish(new StockMovementRecorded(
            StockMovementId::fromString(Ids::next()),
            TenantId::fromString($this->tenant),
            StockItemId::fromString($this->id($shirts)),
            MovementType::Receipt,
            40,
            new \DateTimeImmutable(),
        ));

        self::assertSame($before, $this->request('GET', '/api/stock-items'));
    }

    public function testRebuildsTheReadModelFromTheLedger(): void
    {
        $this->putAwayTheShirts();
        $before = $this->request('GET', '/api/stock-items');
        $this->entityManager()->getConnection()->executeStatement('DELETE FROM stock_overview');
        self::assertSame([], $this->request('GET', '/api/stock-items'));

        $projected = self::getContainer()->get(CommandBus::class)->dispatch(new RebuildStockOverview());

        self::assertSame(4, $projected, 'Shirts at the dock (now empty) and on the shelf, and two kinds of trousers.');
        self::assertSame($before, $this->request('GET', '/api/stock-items'));
    }

    public function testDescribesTheApiInOpenApi(): void
    {
        $paths = $this->openApiPaths();

        foreach (['/api/stock-items', '/api/stock-items/{id}', '/api/stock-movements', '/api/products/{productId}/stock'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
    }

    /**
     * A closed receipt of 40 shirts and 10 trousers, two of them damaged.
     */
    private function closedReceipt(): void
    {
        $receipt = $this->openReceipt($this->registerAsn());
        $this->receive($receipt, self::SHIRT_GTIN, 40);
        $this->receive($receipt, self::TROUSERS_GTIN, 8);
        $this->receive($receipt, self::TROUSERS_GTIN, 2, ['condition' => 'damaged']);
        $this->request('POST', '/api/receipts/'.$this->id($receipt).'/close');
        $this->assertStatus(200);
    }

    /**
     * Closes the receipt and puts the shirts away in the suggested location, A-01-01.
     */
    private function putAwayTheShirts(): void
    {
        $this->closedReceipt();
        $task = $this->request('GET', '/api/putaway-tasks', null, null, ['status' => 'pending'])[0];
        self::assertIsArray($task);
        self::assertSame($this->shirtId, $task['productId']);
        self::assertSame($this->reserveId, $task['suggestedLocationId']);

        $this->request('POST', '/api/putaway-tasks/'.$this->id($task).'/confirm', ['locationId' => $this->reserveId]);
        $this->assertStatus(200);
    }

    private function events(): EventBus
    {
        return self::getContainer()->get(EventBus::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
