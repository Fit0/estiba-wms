<?php

declare(strict_types=1);

namespace App\Inventory\Application\ListStock;

use App\Inventory\Application\StockOverview\StockOverviewEntry;
use App\Inventory\Application\StockOverview\StockOverviewReader;
use App\Inventory\Application\StockOverview\StockSearch;
use App\Inventory\Domain\LocationId;
use App\Inventory\Domain\ProductId;
use App\Inventory\Domain\WarehouseId;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;

final readonly class ListStockHandler implements QueryHandler
{
    public function __construct(
        private StockOverviewReader $stock,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return Page<StockOverviewEntry>
     */
    public function __invoke(ListStock $query): Page
    {
        return $this->stock->search($this->tenant->id(), new StockSearch(
            null === $query->productId ? null : ProductId::fromString($query->productId),
            null === $query->locationId ? null : LocationId::fromString($query->locationId),
            null === $query->warehouseId ? null : WarehouseId::fromString($query->warehouseId),
            $query->status,
        ), $query->page);
    }
}
