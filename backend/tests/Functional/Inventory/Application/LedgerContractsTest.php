<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory\Application;

use App\Inventory\Application\Contract\StockArrival;
use App\Inventory\Application\Contract\StockAtLocation;
use App\Inventory\Application\Contract\StockLevels;
use App\Inventory\Application\Contract\StockMovements;
use App\Inventory\Application\Contract\StockTransfer;
use App\Inventory\Application\LedgerStockLevels;
use App\Inventory\Application\LedgerStockMovements;
use App\Inventory\Domain\StockItem;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineStockItemRepository;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\DomainError;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;
use App\Shared\Infrastructure\Id\UuidV7IdGenerator;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Support\Ids;

/**
 * What the other contexts may ask of Inventory, run on the real ledger.
 */
final class LedgerContractsTest extends PersistenceTestCase
{
    private StockMovements $movements;

    private StockLevels $levels;

    private TenantId $tenantId;

    private string $productId;

    private string $dock;

    private string $shelf;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $stockItems = new DoctrineStockItemRepository($this->entityManager);
        $this->movements = new LedgerStockMovements($stockItems, new UuidV7IdGenerator(), new class implements EventBus {
            // The read model is tested apart; here only the ledger matters.
            #[\Override]
            public function publish(DomainEvent ...$events): void
            {
            }
        });
        $this->levels = new LedgerStockLevels($stockItems);
        $this->tenantId = TenantId::fromString(Ids::next());
        $this->productId = Ids::next();
        $this->dock = Ids::next();
        $this->shelf = Ids::next();
    }

    public function testReceivesStockAndAddsToItOnTheNextArrival(): void
    {
        $this->movements->receive($this->arrival(30));
        $this->movements->receive($this->arrival(10));
        $this->flushAndForget();

        self::assertEquals([new StockAtLocation($this->dock, $this->productId, 40)], $this->levels->at($this->tenantId, [$this->dock]));
        self::assertSame(1, $this->stockItems(), 'Both arrivals went to the same stock item.');
    }

    // INB-10: damaged units are kept apart, in quarantine.
    public function testKeepsQuarantinedUnitsInAStockItemOfTheirOwn(): void
    {
        $this->movements->receive($this->arrival(30));
        $this->movements->receive($this->arrival(2, quarantine: true));
        $this->flushAndForget();

        self::assertSame(2, $this->stockItems());
        self::assertEquals([new StockAtLocation($this->dock, $this->productId, 32)], $this->levels->at($this->tenantId, [$this->dock]), 'The levels add up every status.');
    }

    // STK-08
    public function testTransfersUnitsBetweenLocations(): void
    {
        $this->movements->receive($this->arrival(30, 'L-0425'));
        $this->movements->transfer($this->transfer(12, 'L-0425'));
        $this->flushAndForget();

        self::assertEquals(
            [new StockAtLocation($this->dock, $this->productId, 18), new StockAtLocation($this->shelf, $this->productId, 12)],
            $this->sorted($this->levels->at($this->tenantId, [$this->dock, $this->shelf])),
        );
    }

    // STK-01
    public function testRefusesToTransferWhatIsNotThere(): void
    {
        $this->movements->receive($this->arrival(5));

        try {
            $this->movements->transfer($this->transfer(6));
            self::fail('Six units cannot leave a location that holds five.');
        } catch (DomainError $error) {
            self::assertSame('inventory.stock_negative', $error->errorCode());
        }

        try {
            $this->movements->transfer($this->transfer(1, 'L-OTHER'));
            self::fail('A lot that is not there cannot be moved.');
        } catch (DomainError $error) {
            self::assertSame('inventory.stock_negative', $error->errorCode());
        }
    }

    private function arrival(int $units, ?string $lot = null, bool $quarantine = false): StockArrival
    {
        return new StockArrival($this->tenantId, $this->productId, $this->dock, $lot, null, $quarantine, $units, 'ALB-2027-0153', Ids::next(), new \DateTimeImmutable('2026-10-21T10:00:00+00:00'));
    }

    private function transfer(int $units, ?string $lot = null): StockTransfer
    {
        return new StockTransfer($this->tenantId, $this->productId, $this->dock, $this->shelf, $lot, null, false, $units, 'ALB-2027-0153', Ids::next(), new \DateTimeImmutable('2026-10-21T11:00:00+00:00'));
    }

    private function stockItems(): int
    {
        return \count($this->entityManager->getRepository(StockItem::class)->findAll());
    }

    /**
     * @param list<StockAtLocation> $levels
     *
     * @return list<StockAtLocation>
     */
    private function sorted(array $levels): array
    {
        usort($levels, fn (StockAtLocation $a, StockAtLocation $b): int => ($a->locationId === $this->dock ? 0 : 1) <=> ($b->locationId === $this->dock ? 0 : 1));

        return $levels;
    }
}
