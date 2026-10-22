<?php

declare(strict_types=1);

namespace App\Inventory\Application\ListStockMovements;

use App\Inventory\Application\StockOverview\StockMovementEntry;
use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;

/**
 * The history of the stock: the movements of the tenant, newest first, of a stock item, a product
 * or a location, as the filters say.
 *
 * @implements Query<Page<StockMovementEntry>>
 */
final readonly class ListStockMovements implements Query
{
    public function __construct(
        public PageRequest $page,
        public ?string $stockItemId = null,
        public ?string $productId = null,
        public ?string $locationId = null,
    ) {
    }
}
