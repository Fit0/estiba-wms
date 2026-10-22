<?php

declare(strict_types=1);

namespace App\Inventory\Application\ListStockMovements;

use App\Inventory\Application\StockOverview\MovementSearch;
use App\Inventory\Application\StockOverview\StockMovementEntry;
use App\Inventory\Application\StockOverview\StockMovementHistory;
use App\Inventory\Domain\LocationId;
use App\Inventory\Domain\ProductId;
use App\Inventory\Domain\StockItemId;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;

final readonly class ListStockMovementsHandler implements QueryHandler
{
    public function __construct(
        private StockMovementHistory $movements,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return Page<StockMovementEntry>
     */
    public function __invoke(ListStockMovements $query): Page
    {
        return $this->movements->search($this->tenant->id(), new MovementSearch(
            null === $query->stockItemId ? null : StockItemId::fromString($query->stockItemId),
            null === $query->productId ? null : ProductId::fromString($query->productId),
            null === $query->locationId ? null : LocationId::fromString($query->locationId),
        ), $query->page);
    }
}
