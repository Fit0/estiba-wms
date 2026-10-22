<?php

declare(strict_types=1);

namespace App\Inventory\Application\StockOverview;

/**
 * Units on hand, and how many of them are in each status. Until reservations arrive (E04-01),
 * available is the units in available status; then it will leave out the reserved ones.
 */
final readonly class StockTotals
{
    public function __construct(
        public int $onHand,
        public int $available,
        public int $quarantine,
        public int $damaged,
        public int $onHold,
    ) {
    }

    public static function none(): self
    {
        return new self(0, 0, 0, 0, 0);
    }

    public function plus(self $other): self
    {
        return new self(
            $this->onHand + $other->onHand,
            $this->available + $other->available,
            $this->quarantine + $other->quarantine,
            $this->damaged + $other->damaged,
            $this->onHold + $other->onHold,
        );
    }
}
