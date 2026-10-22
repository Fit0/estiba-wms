<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Resource;

use App\Inventory\Application\StockOverview\WarehouseStock;

/**
 * The stock of a product in one warehouse, as it appears inside {@see ProductStockResource}.
 * `locations` counts the locations that hold some of it.
 */
final readonly class WarehouseStockResource
{
    public function __construct(
        public string $warehouseId,
        public string $warehouseCode,
        public int $onHand,
        public int $available,
        public int $quarantine,
        public int $damaged,
        public int $onHold,
        public int $locations,
    ) {
    }

    public static function fromStock(WarehouseStock $stock): self
    {
        $totals = $stock->totals;

        return new self(
            $stock->warehouseId,
            $stock->warehouseCode,
            $totals->onHand,
            $totals->available,
            $totals->quarantine,
            $totals->damaged,
            $totals->onHold,
            $stock->locations,
        );
    }
}
