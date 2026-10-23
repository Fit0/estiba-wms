<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

/**
 * Part of a reservation that is covered by one stock item.
 */
final readonly class Allocation
{
    public function __construct(
        public StockItemId $stockItemId,
        public Quantity $quantity,
    ) {
    }
}
