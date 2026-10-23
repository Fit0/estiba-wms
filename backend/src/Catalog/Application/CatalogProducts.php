<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Catalog\Application\Contract\ProductSnapshot;
use App\Catalog\Domain\Gtin;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductId;
use App\Catalog\Domain\ProductRepository;
use App\Shared\Domain\TenantId;

/**
 * Answers the {@see ProductCatalog} contract from the product repository.
 */
final readonly class CatalogProducts implements ProductCatalog
{
    public function __construct(private ProductRepository $products)
    {
    }

    #[\Override]
    public function findById(TenantId $tenantId, string $productId): ?ProductSnapshot
    {
        return $this->snapshotOf($tenantId, $this->products->find(ProductId::fromString($productId)));
    }

    #[\Override]
    public function findByGtin(TenantId $tenantId, string $gtin): ?ProductSnapshot
    {
        return $this->snapshotOf($tenantId, $this->products->findByGtin($tenantId, Gtin::fromString($gtin)));
    }

    private function snapshotOf(TenantId $tenantId, ?Product $product): ?ProductSnapshot
    {
        if (!$product instanceof Product || !$product->tenantId()->equals($tenantId)) {
            return null;
        }

        $tracking = $product->trackingPolicy();
        $dimensions = $product->dimensions();

        return new ProductSnapshot(
            $product->id()->value,
            $product->sku()->value,
            $product->name()->value,
            $product->storageClass(),
            $tracking->trackedByLot,
            $tracking->trackedByExpiry,
            $tracking->minShelfLifeAtReceiptDays,
            $tracking->minShelfLifeAtShipmentDays,
            $product->isAvailableForOperations(),
            $product->family(),
            $product->turnoverClass(),
            $dimensions->lengthMm,
            $dimensions->widthMm,
            $dimensions->heightMm,
            $dimensions->weightGrams,
        );
    }
}
