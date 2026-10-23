<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Inventory\Domain\Allocation;
use App\Inventory\Domain\AllocationStrategy;
use App\Inventory\Domain\Quantity;
use App\Inventory\Domain\ReservationId;
use App\Inventory\Domain\ReservationReference;
use App\Inventory\Domain\ReservationStatus;
use App\Inventory\Domain\StockItemId;
use App\Inventory\Domain\StockReservation;
use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

final class StockReservationTest extends TestCase
{
    use AssertsDomainErrors;

    public function testOpensActiveWithNothingCoveredYet(): void
    {
        $reservation = $this->reservation(10);

        self::assertSame(ReservationStatus::Active, $reservation->status());
        self::assertTrue($reservation->allocated()->isZero());
        self::assertSame(10, $reservation->missing()->units, 'All of it is still to cover.');
        self::assertSame([], $reservation->allocations());
    }

    // STK-11
    public function testCannotBeOpenedForZeroUnits(): void
    {
        self::assertDomainError('inventory.reservation_quantity_not_positive', [], fn (): StockReservation => $this->reservation(0));
    }

    // STK-11
    public function testAllocatingAddsUpTheUnitsOfEveryStockItem(): void
    {
        $reservation = $this->reservation(10);

        $reservation->allocate([$this->allocation(6), $this->allocation(3)]);

        self::assertSame(9, $reservation->allocated()->units);
        self::assertSame(1, $reservation->missing()->units, 'Covering less than asked is allowed: the rest is the shortage.');
    }

    // STK-11
    public function testNeverAllocatesMoreThanWasRequested(): void
    {
        $reservation = $this->reservation(10);
        $reservation->allocate([$this->allocation(8)]);

        self::assertDomainError('inventory.allocation_exceeds_requested', ['allocated' => 11, 'requested' => 10], fn () => $reservation->allocate([$this->allocation(3)]));

        self::assertSame(8, $reservation->allocated()->units, 'A refused allocation changes nothing.');
    }

    public function testATopUpAddsToTheStockItemThatAlreadyCoversPart(): void
    {
        $reservation = $this->reservation(10);
        $stockItemId = StockItemId::fromString(Ids::next());
        $reservation->allocate([new Allocation($stockItemId, Quantity::of(4))]);

        $reservation->allocate([new Allocation($stockItemId, Quantity::of(5))]);

        self::assertCount(1, $reservation->allocations(), 'One stock item is one allocation.');
        self::assertSame(9, $reservation->allocations()[0]->quantity->units);
    }

    public function testReleasingGivesBackTheAllocationsAndHoldsNothing(): void
    {
        $reservation = $this->reservation(10);
        $reservation->allocate([$this->allocation(6), $this->allocation(2)]);

        $held = $reservation->release();

        self::assertSame([6, 2], array_map(static fn (Allocation $allocation): int => $allocation->quantity->units, $held));
        self::assertSame(ReservationStatus::Released, $reservation->status());
        self::assertTrue($reservation->allocated()->isZero());
    }

    // STK-14
    public function testReleasingAgainGivesBackNothing(): void
    {
        $reservation = $this->reservation(10);
        $reservation->allocate([$this->allocation(6)]);
        $reservation->release();

        self::assertSame([], $reservation->release(), 'The second release has nothing to give back, so the stock is not freed twice.');
        self::assertSame(ReservationStatus::Released, $reservation->status());
    }

    public function testAReleasedReservationTakesNoMoreUnits(): void
    {
        $reservation = $this->reservation(10);
        $reservation->release();

        self::assertDomainError('inventory.reservation_not_active', ['id' => $reservation->id()->value], fn () => $reservation->allocate([$this->allocation(1)]));
    }

    public function testKeepsWhatItServes(): void
    {
        $tenantId = StockMother::tenantId();
        $productId = StockMother::productId();

        $reservation = StockReservation::open(
            ReservationId::fromString(Ids::next()),
            $tenantId,
            ReservationReference::fromString(' order-1:1 '),
            $productId,
            Quantity::of(4),
            AllocationStrategy::Fefo,
            StockMother::at(),
        );

        self::assertSame('order-1:1', $reservation->reference()->value);
        self::assertTrue($productId->equals($reservation->productId()));
        self::assertTrue($tenantId->equals($reservation->tenantId()));
        self::assertSame(AllocationStrategy::Fefo, $reservation->strategy());
        self::assertSame(4, $reservation->requested()->units);
        self::assertEquals(StockMother::at(), $reservation->createdAt());
    }

    public function testAReferenceHasOneToEightyCharacters(): void
    {
        self::assertSame(80, mb_strlen(ReservationReference::fromString(str_repeat('a', 80))->value));

        foreach (['', '   ', str_repeat('a', 81), "a\x07b"] as $value) {
            self::assertDomainError('inventory.reservation_reference_invalid', ['value' => $value], static fn (): ReservationReference => ReservationReference::fromString($value));
        }
    }

    private function reservation(int $units): StockReservation
    {
        return StockReservation::open(
            ReservationId::fromString(Ids::next()),
            StockMother::tenantId(),
            ReservationReference::fromString('order-'.Ids::next().':1'),
            StockMother::productId(),
            Quantity::of($units),
            AllocationStrategy::Fifo,
            StockMother::at(),
        );
    }

    private function allocation(int $units): Allocation
    {
        return new Allocation(StockItemId::fromString(Ids::next()), Quantity::of($units));
    }
}
