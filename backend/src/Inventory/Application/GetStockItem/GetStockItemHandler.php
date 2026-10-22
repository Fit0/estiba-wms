<?php

declare(strict_types=1);

namespace App\Inventory\Application\GetStockItem;

use App\Inventory\Application\StockOverview\StockOverviewEntry;
use App\Inventory\Application\StockOverview\StockOverviewReader;
use App\Inventory\Domain\Exception\StockItemNotFound;
use App\Inventory\Domain\StockItemId;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;

final readonly class GetStockItemHandler implements QueryHandler
{
    public function __construct(
        private StockOverviewReader $stock,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws StockItemNotFound
     */
    public function __invoke(GetStockItem $query): StockOverviewEntry
    {
        return $this->stock->find($this->tenant->id(), StockItemId::fromString($query->id))
            ?? throw StockItemNotFound::forId($query->id);
    }
}
