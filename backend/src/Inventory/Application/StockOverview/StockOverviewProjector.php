<?php

declare(strict_types=1);

namespace App\Inventory\Application\StockOverview;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Catalog\Application\Contract\ProductSnapshot;
use App\Inventory\Domain\StockItem;
use App\Warehouse\Application\Contract\LocationDirectory;
use App\Warehouse\Application\Contract\LocationSnapshot;

/**
 * Copies a stock item, as it is now, into the read model, with what Catalog and Warehouse say about
 * its product and location (ADR 0013).
 *
 * It never adds up changes: it reads the current state and writes it whole. Projecting the same
 * stock item twice, or two of its movements in the wrong order, leaves the right row.
 */
final readonly class StockOverviewProjector
{
    public function __construct(
        private ProductCatalog $products,
        private LocationDirectory $locations,
        private StockOverviewProjection $projection,
    ) {
    }

    public function project(StockItem $stockItem): void
    {
        $tenantId = $stockItem->tenantId();
        $key = $stockItem->key();

        $product = $this->products->findById($tenantId, $key->productId->value);
        $location = $this->locations->findById($tenantId, $key->locationId->value);
        if (!$product instanceof ProductSnapshot || !$location instanceof LocationSnapshot) {
            throw new \LogicException(\sprintf('Stock item %s holds a product or is in a location that does not exist.', $stockItem->id()->value));
        }

        $this->projection->save(new StockOverviewEntry(
            $stockItem->id()->value,
            $tenantId,
            $product->id,
            $product->sku,
            $product->name,
            $location->warehouseId,
            $location->warehouseCode,
            $location->id,
            $location->code,
            $key->lot?->value,
            $key->expiryDate,
            $key->status,
            $stockItem->onHand()->units,
        ));
    }
}
