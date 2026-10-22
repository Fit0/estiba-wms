<?php

declare(strict_types=1);

namespace App\Inventory\Application\ListStock;

use App\Inventory\Application\StockOverview\StockOverviewEntry;
use App\Inventory\Domain\StockStatus;
use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;

/**
 * The stock of the tenant, one entry per stock item that holds units: of a product, in a location
 * or in a warehouse, as the filters say.
 *
 * @implements Query<Page<StockOverviewEntry>>
 */
final readonly class ListStock implements Query
{
    public function __construct(
        public PageRequest $page,
        public ?string $productId = null,
        public ?string $locationId = null,
        public ?string $warehouseId = null,
        public ?StockStatus $status = null,
    ) {
    }
}
