<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory\Persistence;

use App\Inventory\Domain\Allocation;
use App\Inventory\Domain\AllocationStrategy;
use App\Inventory\Domain\Quantity;
use App\Inventory\Domain\ReservationId;
use App\Inventory\Domain\ReservationReference;
use App\Inventory\Domain\ReservationStatus;
use App\Inventory\Domain\StockItem;
use App\Inventory\Domain\StockReservation;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineStockItemRepository;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineStockReservationRepository;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Inventory\Domain\StockMother;
use App\Tests\Unit\Support\Ids;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;

final class ReservationPersistenceTest extends PersistenceTestCase
{
    private DoctrineStockItemRepository $stockItems;

    private DoctrineStockReservationRepository $reservations;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->stockItems = new DoctrineStockItemRepository($this->entityManager);
        $this->reservations = new DoctrineStockReservationRepository($this->entityManager);
    }

    public function testSavesWhatIsReservedAndWhenUnitsFirstArrived(): void
    {
        $received = new \DateTimeImmutable('2026-09-03T08:30:00+00:00');
        $stockItem = StockMother::stockItemHolding(20, receivedAt: $received);
        $stockItem->reserve(Quantity::of(7));
        $this->stockItems->save($stockItem);
        $this->flushAndForget();

        $loaded = $this->stockItems->find($stockItem->id());

        self::assertInstanceOf(StockItem::class, $loaded);
        self::assertSame(20, $loaded->onHand()->units);
        self::assertSame(7, $loaded->reserved()->units);
        self::assertSame(13, $loaded->available()->units);
        self::assertEquals($received, $loaded->firstReceivedAt());
    }

    // The stock items that existed before reservations had nothing reserved and have a receipt in the ledger.
    public function testAStockItemThatWasNeverReservedHasNothingReserved(): void
    {
        $stockItem = StockMother::stockItemHolding(5);
        $this->stockItems->save($stockItem);
        $this->flushAndForget();

        $loaded = $this->stockItems->find($stockItem->id());

        self::assertInstanceOf(StockItem::class, $loaded);
        self::assertTrue($loaded->reserved()->isZero());
    }

    // STK-02
    public function testTwoChangesToTheSameStockItemCannotBothWin(): void
    {
        $stockItem = StockMother::stockItemHolding(10);
        $this->stockItems->save($stockItem);
        $this->flushAndForget();

        $loaded = $this->stockItems->find($stockItem->id());
        self::assertInstanceOf(StockItem::class, $loaded);

        // Another request saved the same stock item after this one read it.
        $this->entityManager->getConnection()->executeStatement('UPDATE stock_items SET reserved = 6, version = version + 1 WHERE id = ?', [$stockItem->id()->value]);

        $loaded->reserve(Quantity::of(6));
        $this->stockItems->save($loaded);

        $this->expectException(OptimisticLockException::class);
        $this->entityManager->flush();
    }

    public function testEachSaveRaisesTheVersion(): void
    {
        $stockItem = StockMother::stockItemHolding(10);
        $this->stockItems->save($stockItem);
        $this->flushAndForget();
        $first = $this->stockItems->find($stockItem->id());
        self::assertInstanceOf(StockItem::class, $first);
        $before = $first->version();

        $first->reserve(Quantity::of(1));
        $this->stockItems->save($first);
        $this->flushAndForget();

        $second = $this->stockItems->find($stockItem->id());
        self::assertInstanceOf(StockItem::class, $second);
        self::assertSame($before + 1, $second->version());
    }

    public function testSavesAReservationWithItsAllocations(): void
    {
        $tenantId = StockMother::tenantId();
        $productId = StockMother::productId();
        $firstItem = StockMother::stockItemHolding(10)->id();
        $secondItem = StockMother::stockItemHolding(10)->id();
        $reservation = StockReservation::open(ReservationId::fromString(Ids::next()), $tenantId, ReservationReference::fromString('order:1:1'), $productId, Quantity::of(15), AllocationStrategy::Fefo, StockMother::at());
        $reservation->allocate([new Allocation($firstItem, Quantity::of(10)), new Allocation($secondItem, Quantity::of(3))]);
        $this->reservations->save($reservation);
        $this->flushAndForget();

        $loaded = $this->reservations->find($reservation->id());

        self::assertInstanceOf(StockReservation::class, $loaded);
        self::assertSame('order:1:1', $loaded->reference()->value);
        self::assertSame(15, $loaded->requested()->units);
        self::assertSame(AllocationStrategy::Fefo, $loaded->strategy());
        self::assertSame(ReservationStatus::Active, $loaded->status());
        self::assertSame(13, $loaded->allocated()->units);
        self::assertSame(2, $loaded->missing()->units);
        self::assertSame([$firstItem->value, $secondItem->value], array_map(static fn (Allocation $a): string => $a->stockItemId->value, $loaded->allocations()));
        self::assertEquals(StockMother::at(), $loaded->createdAt());
    }

    public function testASavedReleaseHoldsNothing(): void
    {
        $reservation = StockReservation::open(ReservationId::fromString(Ids::next()), StockMother::tenantId(), ReservationReference::fromString('order:1:1'), StockMother::productId(), Quantity::of(4), AllocationStrategy::Fifo, StockMother::at());
        $reservation->allocate([new Allocation(StockMother::stockItemHolding(10)->id(), Quantity::of(4))]);
        $this->reservations->save($reservation);
        $this->flushAndForget();

        $loaded = $this->reservations->find($reservation->id());
        self::assertInstanceOf(StockReservation::class, $loaded);
        $loaded->release();
        $this->reservations->save($loaded);
        $this->flushAndForget();

        $again = $this->reservations->find($reservation->id());
        self::assertInstanceOf(StockReservation::class, $again);
        self::assertSame(ReservationStatus::Released, $again->status());
        self::assertSame([], $again->allocations());
    }

    public function testFindsAReservationByItsReferenceWithinTheTenant(): void
    {
        $tenantId = StockMother::tenantId();
        $reference = ReservationReference::fromString('order:1:1');
        $reservation = StockReservation::open(ReservationId::fromString(Ids::next()), $tenantId, $reference, StockMother::productId(), Quantity::of(4), AllocationStrategy::Fifo, StockMother::at());
        $this->reservations->save($reservation);
        $this->flushAndForget();

        self::assertInstanceOf(StockReservation::class, $this->reservations->findByReference($tenantId, $reference));
        self::assertNull($this->reservations->findByReference(StockMother::tenantId(), $reference), 'Another tenant has no such reservation.');
        self::assertNull($this->reservations->findByReference($tenantId, ReservationReference::fromString('order:2:1')));
    }

    // A reference has one reservation: the database backs the lookup against two requests racing.
    public function testAReferenceCannotHaveTwoReservationsInTheSameTenant(): void
    {
        $tenantId = StockMother::tenantId();
        $reference = ReservationReference::fromString('order:1:1');
        foreach ([1, 2] as $ignored) {
            $this->reservations->save(StockReservation::open(ReservationId::fromString(Ids::next()), $tenantId, $reference, StockMother::productId(), Quantity::of(4), AllocationStrategy::Fifo, StockMother::at()));
        }

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }
}
