<?php

declare(strict_types=1);

namespace App\Inventory\Application\StockOverview;

use App\Inventory\Domain\LocationId;
use App\Inventory\Domain\ProductId;
use App\Inventory\Domain\StockItemId;

/**
 * Which movements to list. Every criterion that is given must match.
 */
final readonly class MovementSearch
{
    public function __construct(
        public ?StockItemId $stockItemId = null,
        public ?ProductId $productId = null,
        public ?LocationId $locationId = null,
    ) {
    }
}
