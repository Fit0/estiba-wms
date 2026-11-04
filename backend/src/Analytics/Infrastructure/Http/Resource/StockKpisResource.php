<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure\Http\Resource;

/**
 * Units on hand right now, and how many are in each status. `damaged` is the stock written off as
 * unfit for sale that has not left the warehouse yet.
 */
final readonly class StockKpisResource
{
    public function __construct(
        public int $onHand,
        public int $available,
        public int $quarantine,
        public int $damaged,
        public int $onHold,
    ) {
    }
}
