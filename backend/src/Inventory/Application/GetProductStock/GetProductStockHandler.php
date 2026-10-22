<?php

declare(strict_types=1);

namespace App\Inventory\Application\GetProductStock;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Inventory\Application\StockOverview\ProductStock;
use App\Inventory\Application\StockOverview\StockOverviewReader;
use App\Inventory\Application\StockOverview\StockTotals;
use App\Inventory\Domain\Exception\ProductNotFound;
use App\Inventory\Domain\ProductId;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;

/**
 * The SKU and name come from the catalog, as they are now; the units from the read model.
 */
final readonly class GetProductStockHandler implements QueryHandler
{
    public function __construct(
        private StockOverviewReader $stock,
        private ProductCatalog $products,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws ProductNotFound
     */
    public function __invoke(GetProductStock $query): ProductStock
    {
        $tenantId = $this->tenant->id();
        $productId = ProductId::fromString($query->productId);
        $product = $this->products->findById($tenantId, $productId->value)
            ?? throw ProductNotFound::forId($query->productId);

        $warehouses = $this->stock->stockOfProduct($tenantId, $productId);
        $totals = StockTotals::none();
        foreach ($warehouses as $warehouse) {
            $totals = $totals->plus($warehouse->totals);
        }

        return new ProductStock($product->id, $product->sku, $product->name, $totals, $warehouses);
    }
}
