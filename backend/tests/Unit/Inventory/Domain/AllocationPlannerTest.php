<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Inventory\Domain\Allocation;
use App\Inventory\Domain\AllocationPlanner;
use App\Inventory\Domain\AllocationStrategy;
use App\Inventory\Domain\Quantity;
use App\Inventory\Domain\StockItem;
use App\Inventory\Domain\StockStatus;
use PHPUnit\Framework\TestCase;

/**
 * Which stock is promised first (STK-12).
 */
final class AllocationPlannerTest extends TestCase
{
    private AllocationPlanner $planner;

    #[\Override]
    protected function setUp(): void
    {
        $this->planner = new AllocationPlanner();
    }

    public function testFefoTakesTheStockThatExpiresFirst(): void
    {
        $later = $this->stock(10, expiry: '2027-06-30', received: '2026-09-01');
        $sooner = $this->stock(10, expiry: '2027-02-28', received: '2026-10-05');

        $plan = $this->planner->plan([$later, $sooner], Quantity::of(10), AllocationStrategy::Fefo, null);

        self::assertSame([$sooner->id()->value => 10], $this->units($plan), 'It arrived later but expires sooner.');
    }

    public function testFefoUsesTheOldestArrivalAmongEqualExpiryDates(): void
    {
        $newer = $this->stock(10, expiry: '2027-02-28', received: '2026-10-05');
        $older = $this->stock(10, expiry: '2027-02-28', received: '2026-09-01');

        $plan = $this->planner->plan([$newer, $older], Quantity::of(4), AllocationStrategy::Fefo, null);

        self::assertSame([$older->id()->value => 4], $this->units($plan));
    }

    public function testFefoUsesStockThatNeverExpiresLast(): void
    {
        $forever = $this->stock(10, received: '2026-08-01');
        $expires = $this->stock(10, expiry: '2028-01-31', received: '2026-10-05');

        $plan = $this->planner->plan([$forever, $expires], Quantity::of(15), AllocationStrategy::Fefo, null);

        self::assertSame([$expires->id()->value => 10, $forever->id()->value => 5], $this->units($plan));
    }

    public function testFifoTakesTheOldestArrivalWhateverItsExpiryDate(): void
    {
        $newer = $this->stock(10, expiry: '2027-02-28', received: '2026-10-05');
        $older = $this->stock(10, expiry: '2027-06-30', received: '2026-09-01');

        $plan = $this->planner->plan([$newer, $older], Quantity::of(10), AllocationStrategy::Fifo, null);

        self::assertSame([$older->id()->value => 10], $this->units($plan));
    }

    public function testCoversTheQuantityWithSeveralStockItemsInOrder(): void
    {
        $first = $this->stock(6, received: '2026-09-01');
        $second = $this->stock(6, received: '2026-09-02');
        $third = $this->stock(6, received: '2026-09-03');

        $plan = $this->planner->plan([$third, $first, $second], Quantity::of(14), AllocationStrategy::Fifo, null);

        self::assertSame([$first->id()->value => 6, $second->id()->value => 6, $third->id()->value => 2], $this->units($plan));
    }

    // STK-11: a shortage is not an error
    public function testGivesWhatThereIsWhenThereIsNotEnough(): void
    {
        $only = $this->stock(5, received: '2026-09-01');

        $plan = $this->planner->plan([$only], Quantity::of(8), AllocationStrategy::Fifo, null);

        self::assertSame([$only->id()->value => 5], $this->units($plan));
    }

    public function testGivesNothingWhenThereIsNoStock(): void
    {
        self::assertSame([], $this->planner->plan([], Quantity::of(3), AllocationStrategy::Fifo, null));
    }

    public function testNeverAsksForMoreThanIsNeeded(): void
    {
        $plan = $this->planner->plan([$this->stock(100, received: '2026-09-01')], Quantity::of(3), AllocationStrategy::Fifo, null);

        self::assertSame(3, array_sum($this->units($plan)));
    }

    // STK-02: the units already promised to someone else are not offered again
    public function testOnlyOffersTheUnitsNobodyHasPromised(): void
    {
        $partly = $this->stock(10, received: '2026-09-01');
        $partly->reserve(Quantity::of(7));
        $free = $this->stock(10, received: '2026-09-02');

        $plan = $this->planner->plan([$partly, $free], Quantity::of(8), AllocationStrategy::Fifo, null);

        self::assertSame([$partly->id()->value => 3, $free->id()->value => 5], $this->units($plan));
    }

    public function testSkipsStockWithNoFreeUnit(): void
    {
        $taken = $this->stock(5, received: '2026-09-01');
        $taken->reserve(Quantity::of(5));
        $free = $this->stock(5, received: '2026-09-02');

        $plan = $this->planner->plan([$taken, $free], Quantity::of(5), AllocationStrategy::Fifo, null);

        self::assertSame([$free->id()->value => 5], $this->units($plan));
    }

    // STK-06
    public function testSkipsStockThatIsNotAvailable(): void
    {
        $blocked = StockMother::stockItemHolding(10, key: StockMother::key(status: StockStatus::Quarantine), receivedAt: new \DateTimeImmutable('2026-08-01'));
        $good = $this->stock(10, received: '2026-09-01');

        $plan = $this->planner->plan([$blocked, $good], Quantity::of(10), AllocationStrategy::Fifo, null);

        self::assertSame([$good->id()->value => 10], $this->units($plan));
    }

    // STK-12: stock that would reach the customer with too little shelf life
    public function testSkipsStockThatExpiresBeforeTheEarliestAcceptableDay(): void
    {
        $tooSoon = $this->stock(10, expiry: '2026-12-01', received: '2026-09-01');
        $fine = $this->stock(10, expiry: '2027-03-01', received: '2026-10-05');

        $plan = $this->planner->plan([$tooSoon, $fine], Quantity::of(10), AllocationStrategy::Fefo, new \DateTimeImmutable('2027-01-15'));

        self::assertSame([$fine->id()->value => 10], $this->units($plan));
    }

    public function testStockExpiringOnTheEarliestAcceptableDayIsStillGood(): void
    {
        $onTheDay = $this->stock(10, expiry: '2027-01-15', received: '2026-09-01');

        $plan = $this->planner->plan([$onTheDay], Quantity::of(10), AllocationStrategy::Fefo, new \DateTimeImmutable('2027-01-15'));

        self::assertSame([$onTheDay->id()->value => 10], $this->units($plan));
    }

    public function testStockThatNeverExpiresIsNotAffectedByTheShelfLife(): void
    {
        $forever = $this->stock(10, received: '2026-09-01');

        $plan = $this->planner->plan([$forever], Quantity::of(10), AllocationStrategy::Fefo, new \DateTimeImmutable('2027-01-15'));

        self::assertSame([$forever->id()->value => 10], $this->units($plan));
    }

    public function testTheSameStockAlwaysGivesTheSamePlan(): void
    {
        $a = $this->stock(5, received: '2026-09-01');
        $b = $this->stock(5, received: '2026-09-01');

        $one = $this->planner->plan([$a, $b], Quantity::of(5), AllocationStrategy::Fifo, null);
        $other = $this->planner->plan([$b, $a], Quantity::of(5), AllocationStrategy::Fifo, null);

        self::assertEquals($one, $other, 'Equal arrivals are told apart by identifier, not by the order they were given.');
    }

    private function stock(int $units, ?string $expiry = null, string $received = '2026-09-01'): StockItem
    {
        return StockMother::stockItemHolding(
            $units,
            key: StockMother::key(lot: null === $expiry ? null : 'L-'.$expiry, expiryDate: null === $expiry ? null : new \DateTimeImmutable($expiry)),
            receivedAt: new \DateTimeImmutable($received),
        );
    }

    /**
     * @param list<Allocation> $plan
     *
     * @return array<string, int> the units chosen from each stock item, in the order of the plan
     */
    private function units(array $plan): array
    {
        $units = [];
        foreach ($plan as $allocation) {
            $units[$allocation->stockItemId->value] = $allocation->quantity->units;
        }

        return $units;
    }
}
