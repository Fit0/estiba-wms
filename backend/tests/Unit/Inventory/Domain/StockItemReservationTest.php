<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Inventory\Domain\AdjustmentReason;
use App\Inventory\Domain\Quantity;
use App\Inventory\Domain\StockStatus;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\TestCase;

/**
 * Promising units of a stock item to orders without moving them (STK-02, STK-06, STK-10).
 */
final class StockItemReservationTest extends TestCase
{
    use AssertsDomainErrors;

    public function testReservingLeavesTheUnitsWhereTheyAreAndFreesFewer(): void
    {
        $stockItem = StockMother::stockItemHolding(30);

        $stockItem->reserve(Quantity::of(12));

        self::assertSame(30, $stockItem->onHand()->units);
        self::assertSame(12, $stockItem->reserved()->units);
        self::assertSame(18, $stockItem->available()->units);
    }

    // The units do not move, so the ledger records nothing.
    public function testReservingRecordsNoMovementAndRaisesNoEvent(): void
    {
        $stockItem = StockMother::stockItemHolding(30);

        $stockItem->reserve(Quantity::of(12));
        $stockItem->unreserve(Quantity::of(5));

        self::assertSame([], $stockItem->pullMovements());
        self::assertSame([], $stockItem->pullEvents());
    }

    // STK-02
    public function testCanReserveEverythingButNotMoreThanIsFree(): void
    {
        $stockItem = StockMother::stockItemHolding(10);

        $stockItem->reserve(Quantity::of(10));
        self::assertTrue($stockItem->available()->isZero());

        self::assertDomainError('inventory.not_enough_available_stock', ['requested' => 1, 'available' => 0], static fn () => $stockItem->reserve(Quantity::of(1)));
    }

    // STK-02
    public function testReservedUnitsAreNotFreeForAnotherReservation(): void
    {
        $stockItem = StockMother::stockItemHolding(10);
        $stockItem->reserve(Quantity::of(7));

        self::assertDomainError('inventory.not_enough_available_stock', ['requested' => 4, 'available' => 3], static fn () => $stockItem->reserve(Quantity::of(4)));
        self::assertSame(7, $stockItem->reserved()->units, 'A refused reservation changes nothing.');
    }

    // STK-11
    public function testAReservationOfZeroUnitsIsRefused(): void
    {
        $stockItem = StockMother::stockItemHolding(10);

        self::assertDomainError('inventory.reservation_quantity_not_positive', [], static fn () => $stockItem->reserve(Quantity::zero()));
    }

    // STK-06
    public function testOnlyAvailableStockCanBeReserved(): void
    {
        foreach ([StockStatus::Quarantine, StockStatus::Damaged, StockStatus::OnHold] as $status) {
            $stockItem = StockMother::stockItemHolding(10, key: StockMother::key(status: $status));

            self::assertDomainError('inventory.stock_not_reservable', ['stockItemId' => $stockItem->id()->value, 'status' => $status->value], static fn () => $stockItem->reserve(Quantity::of(1)));
            self::assertTrue($stockItem->reserved()->isZero());
        }
    }

    public function testUnreservingFreesTheUnitsAgain(): void
    {
        $stockItem = StockMother::stockItemHolding(10);
        $stockItem->reserve(Quantity::of(8));

        $stockItem->unreserve(Quantity::of(3));

        self::assertSame(5, $stockItem->reserved()->units);
        self::assertSame(5, $stockItem->available()->units);
    }

    // STK-02
    public function testCannotFreeMoreThanIsReserved(): void
    {
        $stockItem = StockMother::stockItemHolding(10);
        $stockItem->reserve(Quantity::of(2));

        self::assertDomainError('inventory.release_exceeds_reserved', ['requested' => 3, 'reserved' => 2], static fn () => $stockItem->unreserve(Quantity::of(3)));
        self::assertSame(2, $stockItem->reserved()->units);
    }

    // STK-02, STK-10: units promised to an order cannot be given to someone else
    public function testPromisedUnitsCannotBeIssued(): void
    {
        $stockItem = StockMother::stockItemHolding(10);
        $stockItem->reserve(Quantity::of(6));

        $stockItem->issue(StockMother::movementId(), Quantity::of(4), null, StockMother::author(), StockMother::at());
        self::assertSame(6, $stockItem->onHand()->units);

        self::assertDomainError('inventory.not_enough_available_stock', ['requested' => 1, 'available' => 0], static fn () => $stockItem->issue(StockMother::movementId(), Quantity::of(1), null, StockMother::author(), StockMother::at()));
        self::assertSame(6, $stockItem->onHand()->units);
        self::assertCount(1, $stockItem->pullMovements(), 'Only the first issue was recorded: the refused one left no trace.');
    }

    // STK-02
    public function testPromisedUnitsCannotBeAdjustedAway(): void
    {
        $stockItem = StockMother::stockItemHolding(10);
        $stockItem->reserve(Quantity::of(8));

        self::assertDomainError('inventory.not_enough_available_stock', ['requested' => 3, 'available' => 2], static fn () => $stockItem->adjust(StockMother::movementId(), -3, AdjustmentReason::fromString('Recuento'), StockMother::author(), StockMother::at()));

        $stockItem->adjust(StockMother::movementId(), -2, AdjustmentReason::fromString('Recuento'), StockMother::author(), StockMother::at());
        self::assertSame(8, $stockItem->onHand()->units);
    }

    // STK-10: moving units to another status would hide them from the order that was promised them
    public function testPromisedUnitsCannotBeTransferredAway(): void
    {
        $tenantId = StockMother::tenantId();
        $productId = StockMother::productId();
        $source = StockMother::stockItemHolding(10, $tenantId, StockMother::key($productId));
        $destination = StockMother::stockItemHolding(1, $tenantId, StockMother::key($productId, status: StockStatus::Quarantine));
        $source->reserve(Quantity::of(7));

        self::assertDomainError('inventory.not_enough_available_stock', ['requested' => 4, 'available' => 3], static fn () => $source->transferTo($destination, Quantity::of(4), StockMother::movementId(), StockMother::movementId(), null, StockMother::author(), StockMother::at()));

        self::assertSame(10, $source->onHand()->units);
        self::assertSame(1, $destination->onHand()->units, 'The destination was not touched either (STK-08).');
    }

    // STK-02: more units arriving never breaks the promise
    public function testReceivingMoreFreesMoreUnits(): void
    {
        $stockItem = StockMother::stockItemHolding(5);
        $stockItem->reserve(Quantity::of(5));

        $stockItem->receive(StockMother::movementId(), Quantity::of(20), null, StockMother::author(), StockMother::at());

        self::assertSame(20, $stockItem->available()->units);
    }

    // STK-12: FIFO orders stock by when units first arrived
    public function testRemembersWhenUnitsFirstArrived(): void
    {
        $first = new \DateTimeImmutable('2026-10-01T08:00:00+00:00');
        $stockItem = StockMother::stockItem();
        self::assertNull($stockItem->firstReceivedAt());

        $stockItem->receive(StockMother::movementId(), Quantity::of(5), null, StockMother::author(), $first);
        $stockItem->receive(StockMother::movementId(), Quantity::of(5), null, StockMother::author(), new \DateTimeImmutable('2026-10-09T08:00:00+00:00'));

        self::assertEquals($first, $stockItem->firstReceivedAt(), 'Later arrivals do not move it.');
    }

    public function testAnAdjustmentUpAlsoCountsAsAnArrival(): void
    {
        $stockItem = StockMother::stockItem();

        $stockItem->adjust(StockMother::movementId(), 3, AdjustmentReason::fromString('Recuento'), StockMother::author(), StockMother::at());

        self::assertEquals(StockMother::at(), $stockItem->firstReceivedAt());
    }
}
