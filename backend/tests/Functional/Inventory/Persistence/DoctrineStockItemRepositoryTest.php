<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory\Persistence;

use App\Inventory\Domain\AdjustmentReason;
use App\Inventory\Domain\LocationId;
use App\Inventory\Domain\MovementReference;
use App\Inventory\Domain\MovementType;
use App\Inventory\Domain\Quantity;
use App\Inventory\Domain\StockItem;
use App\Inventory\Domain\StockItemId;
use App\Inventory\Domain\StockMovement;
use App\Inventory\Domain\StockStatus;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineStockItemRepository;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Inventory\Domain\StockMother;
use App\Tests\Unit\Support\Ids;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineStockItemRepositoryTest extends PersistenceTestCase
{
    private DoctrineStockItemRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DoctrineStockItemRepository($this->entityManager);
    }

    // STK-03: the change and its movement are saved together.
    public function testSavesAStockItemWithTheMovementsItRecorded(): void
    {
        $stockItem = StockMother::stockItem(null, StockMother::key(lot: 'L-0425', expiryDate: new \DateTimeImmutable('2027-03-31')));
        $stockItem->receive(StockMother::movementId(), Quantity::of(40), MovementReference::fromString('ALB-2027-0153'), StockMother::author(), StockMother::at());
        $stockItem->issue(StockMother::movementId(), Quantity::of(15), null, StockMother::author(), StockMother::at());
        $this->repository->save($stockItem);
        $this->flushAndForget();

        $found = $this->repository->find($stockItem->id());

        self::assertNotNull($found);
        self::assertSame(25, $found->onHand()->units);
        self::assertTrue($found->key()->equals($stockItem->key()));
        self::assertSame('L-0425', $found->key()->lot?->value);
        self::assertSame([MovementType::Receipt, MovementType::Issue], array_map(static fn (StockMovement $movement): MovementType => $movement->type, $this->movementsOf($stockItem->id())));
        self::assertSame([], $found->pullMovements(), 'A loaded stock item has no movements of its own to save.');
    }

    // STK-05: the movements add up to what the stock item holds.
    public function testTheLedgerAddsUpToTheStock(): void
    {
        $stockItem = StockMother::stockItem();
        $stockItem->receive(StockMother::movementId(), Quantity::of(10), null, StockMother::author(), StockMother::at());
        $stockItem->issue(StockMother::movementId(), Quantity::of(3), null, StockMother::author(), StockMother::at());
        $stockItem->adjust(StockMother::movementId(), -2, AdjustmentReason::fromString('Recuento'), StockMother::author(), StockMother::at());
        $this->repository->save($stockItem);
        $this->flushAndForget();

        $found = $this->repository->find($stockItem->id());
        self::assertNotNull($found);
        $sum = array_sum(array_map(static fn (StockMovement $movement): int => $movement->change, $this->movementsOf($stockItem->id())));

        self::assertSame(5, $found->onHand()->units);
        self::assertSame($found->onHand()->units, $sum);
    }

    // DD-1: a stock item without lot or expiry date is found by its key.
    public function testFindsAStockItemByItsKeyEvenWithoutLotOrExpiryDate(): void
    {
        $tenantId = StockMother::tenantId();
        $key = StockMother::key();
        $stockItem = StockMother::stockItemHolding(5, $tenantId, $key);
        $this->repository->save($stockItem);
        $this->flushAndForget();

        $found = $this->repository->findByKey($tenantId, $key);

        self::assertNotNull($found);
        self::assertTrue($found->id()->equals($stockItem->id()));
        self::assertNull($found->key()->lot);
        self::assertNull($found->key()->expiryDate);
        self::assertNull($this->repository->findByKey($tenantId, StockMother::key($key->productId, $key->locationId, StockStatus::Quarantine)));
        self::assertNull($this->repository->findByKey(StockMother::tenantId(), $key));
    }

    public function testFindsAStockItemOpenedEarlierInTheSameTransaction(): void
    {
        $tenantId = StockMother::tenantId();
        $key = StockMother::key();
        $this->repository->save(StockItem::open(StockItemId::fromString(Ids::next()), $tenantId, $key));

        self::assertNotNull($this->repository->findByKey($tenantId, $key));
    }

    // DD-1: the database refuses two stock items with the same key, NULL-free lot and expiry included.
    public function testRejectsTwoStockItemsWithTheSameKey(): void
    {
        $tenantId = StockMother::tenantId();
        $key = StockMother::key();
        $this->repository->save(StockMother::stockItemHolding(1, $tenantId, $key));
        $this->flushAndForget();

        $this->entityManager->persist(StockItem::open(StockItemId::fromString(Ids::next()), $tenantId, $key));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }

    public function testFindsTheStockHeldInSomeLocations(): void
    {
        $tenantId = StockMother::tenantId();
        $dock = LocationId::fromString(Ids::next());
        $shelf = LocationId::fromString(Ids::next());
        $elsewhere = LocationId::fromString(Ids::next());
        $this->repository->save(StockMother::stockItemHolding(5, $tenantId, StockMother::key(locationId: $dock)));
        $this->repository->save(StockMother::stockItemHolding(3, $tenantId, StockMother::key(locationId: $shelf)));
        $this->repository->save(StockMother::stockItemHolding(7, $tenantId, StockMother::key(locationId: $elsewhere)));
        $this->repository->save(StockMother::stockItem($tenantId, StockMother::key(locationId: $shelf)));
        $this->flushAndForget();

        $found = $this->repository->findAt($tenantId, [$dock, $shelf]);

        self::assertCount(2, $found, 'Empty stock items are left out.');
        self::assertSame([], $this->repository->findAt(StockMother::tenantId(), [$dock]));
    }

    /**
     * @return list<StockMovement>
     */
    private function movementsOf(StockItemId $id): array
    {
        /** @var list<StockMovement> $movements */
        $movements = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(StockMovement::class, 'm')
            ->where('m.stockItemId = :id')
            ->setParameter('id', $id, 'stock_item_id')
            ->orderBy('m.occurredAt')
            ->addOrderBy('m.id')
            ->getQuery()
            ->getResult();

        return $movements;
    }
}
