<?php

declare(strict_types=1);

namespace App\Inbound\Application;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Catalog\Application\Contract\ProductSnapshot;
use App\Inbound\Domain\Exception\ProductNotReceivable;
use App\Inbound\Domain\ProductId;
use App\Inbound\Domain\ReceivableProduct;
use App\Shared\Application\CurrentTenant;

/**
 * Asks the catalog about the products of the current tenant and translates the answer into what
 * receiving needs. Whether the product may be received is left to the domain to decide (INB-01).
 */
final readonly class ReceivableProducts
{
    public function __construct(
        private ProductCatalog $catalog,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws ProductNotReceivable when the catalog has no such product
     */
    public function byId(string $productId): ReceivableProduct
    {
        $snapshot = $this->catalog->findById($this->tenant->id(), $productId);

        return $snapshot instanceof ProductSnapshot ? $this->from($snapshot) : throw ProductNotReceivable::unknown($productId);
    }

    /**
     * @throws ProductNotReceivable when no product carries the barcode
     */
    public function byGtin(string $gtin): ReceivableProduct
    {
        $snapshot = $this->catalog->findByGtin($this->tenant->id(), $gtin);

        return $snapshot instanceof ProductSnapshot ? $this->from($snapshot) : throw ProductNotReceivable::unknownGtin($gtin);
    }

    private function from(ProductSnapshot $snapshot): ReceivableProduct
    {
        return new ReceivableProduct(
            ProductId::fromString($snapshot->id),
            $snapshot->availableForOperations,
            $snapshot->trackedByLot,
            $snapshot->trackedByExpiry,
        );
    }
}
