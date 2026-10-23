<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

/**
 * Chooses which stock items cover a quantity (STK-12). It only decides: reserving the units in
 * the stock items is done by the caller, with the plan.
 *
 * - FEFO: the stock that expires first goes first. Among equal expiry dates, and for stock with no
 *   expiry date, the oldest arrival goes first.
 * - FIFO: the oldest arrival goes first.
 *
 * Stock that is not available, or has no free unit, is never chosen. Stock that would expire before
 * `$earliestExpiry` is skipped, because it would not reach the customer with the shelf life the
 * product promises. Stock with the same expiry date and arrival is chosen in the order of its
 * identifier, so the same stock always gives the same plan.
 */
final class AllocationPlanner
{
    /**
     * @param list<StockItem> $candidates
     *
     * @return list<Allocation> as many units as the candidates can give, up to `$needed`
     */
    public function plan(array $candidates, Quantity $needed, AllocationStrategy $strategy, ?\DateTimeImmutable $earliestExpiry): array
    {
        $usable = array_values(array_filter($candidates, fn (StockItem $stockItem): bool => $this->isUsable($stockItem, $earliestExpiry)));
        usort($usable, fn (StockItem $a, StockItem $b): int => $this->order($a, $b, $strategy));

        $plan = [];
        $left = $needed;

        foreach ($usable as $stockItem) {
            if ($left->isZero()) {
                break;
            }

            $take = min($left->units, $stockItem->available()->units);
            $plan[] = new Allocation($stockItem->id(), Quantity::of($take));
            $left = Quantity::of($left->units - $take);
        }

        return $plan;
    }

    private function isUsable(StockItem $stockItem, ?\DateTimeImmutable $earliestExpiry): bool
    {
        if (StockStatus::Available !== $stockItem->key()->status || $stockItem->available()->isZero()) {
            return false;
        }

        $expiryDate = $stockItem->key()->expiryDate;

        return !($earliestExpiry instanceof \DateTimeImmutable && $expiryDate instanceof \DateTimeImmutable && $expiryDate < $earliestExpiry);
    }

    private function order(StockItem $a, StockItem $b, AllocationStrategy $strategy): int
    {
        if (AllocationStrategy::Fefo === $strategy) {
            $byExpiry = $this->compareMoments($a->key()->expiryDate, $b->key()->expiryDate);
            if (0 !== $byExpiry) {
                return $byExpiry;
            }
        }

        $byArrival = $this->compareMoments($a->firstReceivedAt(), $b->firstReceivedAt());

        return 0 !== $byArrival ? $byArrival : strcmp($a->id()->value, $b->id()->value);
    }

    /**
     * Earlier first. A missing moment goes last: stock that never expires is used after stock that does.
     */
    private function compareMoments(?\DateTimeImmutable $a, ?\DateTimeImmutable $b): int
    {
        if (!$a instanceof \DateTimeImmutable) {
            return $b instanceof \DateTimeImmutable ? 1 : 0;
        }

        return $b instanceof \DateTimeImmutable ? $a <=> $b : -1;
    }
}
