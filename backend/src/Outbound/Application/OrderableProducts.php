<?php

declare(strict_types=1);

namespace App\Outbound\Application;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Catalog\Application\Contract\ProductSnapshot;
use App\Outbound\Domain\Exception\ProductNotOrderable;
use App\Outbound\Domain\OrderableProduct;
use App\Outbound\Domain\ProductId;
use App\Shared\Application\CurrentTenant;

/**
 * Asks the catalog about the products of the current tenant and translates the answer into what
 * an order needs. Whether the product may be ordered is left to the domain to decide (OUT-02).
 */
final readonly class OrderableProducts
{
    public function __construct(
        private ProductCatalog $catalog,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws ProductNotOrderable when the catalog has no such product
     */
    public function byId(string $productId): OrderableProduct
    {
        $snapshot = $this->catalog->findById($this->tenant->id(), $productId);

        return $snapshot instanceof ProductSnapshot
            ? new OrderableProduct(ProductId::fromString($snapshot->id), $snapshot->availableForOperations)
            : throw ProductNotOrderable::unknown($productId);
    }
}
