<?php

declare(strict_types=1);

namespace App\Inventory\Application\StockOverview;

use App\Inventory\Domain\ProductId;
use App\Inventory\Domain\StockItemId;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;

/**
 * How the queries read the stock read model (ADR 0013). It lags the ledger by the moment the worker
 * takes to project a movement.
 */
interface StockOverviewReader
{
    /**
     * The stock items of the tenant that hold at least one unit, by SKU, location code, expiry date
     * and lot.
     *
     * @return Page<StockOverviewEntry>
     */
    public function search(TenantId $tenantId, StockSearch $search, PageRequest $page): Page;

    public function find(TenantId $tenantId, StockItemId $id): ?StockOverviewEntry;

    /**
     * What each warehouse holds of the product, by warehouse code. A warehouse with none of it is left out.
     *
     * @return list<WarehouseStock>
     */
    public function stockOfProduct(TenantId $tenantId, ProductId $productId): array;
}
