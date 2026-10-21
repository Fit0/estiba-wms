<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

/**
 * How many units of a product a location holds, adding up its lots and statuses.
 */
final readonly class StockAtLocation
{
    public function __construct(
        public string $locationId,
        public string $productId,
        public int $units,
    ) {
    }
}
