<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

/**
 * The units a tenant has on hand across all its warehouses, and how many of them are in each status.
 */
final readonly class StockSummary
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
