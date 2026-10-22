<?php

declare(strict_types=1);

namespace App\Inventory\Application\StockOverview;

/**
 * The stock of one product in one warehouse, and in how many of its locations it is.
 */
final readonly class WarehouseStock
{
    public function __construct(
        public string $warehouseId,
        public string $warehouseCode,
        public StockTotals $totals,
        public int $locations,
    ) {
    }
}
