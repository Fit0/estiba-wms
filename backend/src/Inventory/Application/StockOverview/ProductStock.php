<?php

declare(strict_types=1);

namespace App\Inventory\Application\StockOverview;

/**
 * How much of a product the tenant holds, in total and in each warehouse that holds some.
 */
final readonly class ProductStock
{
    /**
     * @param list<WarehouseStock> $warehouses by warehouse code
     */
    public function __construct(
        public string $productId,
        public string $sku,
        public string $name,
        public StockTotals $totals,
        public array $warehouses,
    ) {
    }
}
