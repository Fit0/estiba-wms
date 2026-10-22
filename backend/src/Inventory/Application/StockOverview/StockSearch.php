<?php

declare(strict_types=1);

namespace App\Inventory\Application\StockOverview;

use App\Inventory\Domain\LocationId;
use App\Inventory\Domain\ProductId;
use App\Inventory\Domain\StockStatus;
use App\Inventory\Domain\WarehouseId;

/**
 * Which stock items to list. Every criterion that is given must match.
 */
final readonly class StockSearch
{
    public function __construct(
        public ?ProductId $productId = null,
        public ?LocationId $locationId = null,
        public ?WarehouseId $warehouseId = null,
        public ?StockStatus $status = null,
    ) {
    }
}
