<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Inventory\Application\StockOverview\ProductStock;
use App\Inventory\Infrastructure\Http\State\ProductStockProvider;

/**
 * How much the tenant holds of a product, in total and by warehouse. `available` is the units in
 * available status; once reservations arrive (E04-01) it will leave out the reserved ones.
 */
#[ApiResource(
    shortName: 'ProductStock',
    operations: [
        new Get(uriTemplate: '/products/{productId}/stock', uriVariables: ['productId'], provider: ProductStockProvider::class),
    ],
)]
final readonly class ProductStockResource
{
    /**
     * @param list<WarehouseStockResource> $warehouses
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $productId,
        public string $sku,
        public string $name,
        public int $onHand,
        public int $available,
        public int $quarantine,
        public int $damaged,
        public int $onHold,
        public array $warehouses,
    ) {
    }

    public static function fromStock(ProductStock $stock): self
    {
        $totals = $stock->totals;

        return new self(
            $stock->productId,
            $stock->sku,
            $stock->name,
            $totals->onHand,
            $totals->available,
            $totals->quarantine,
            $totals->damaged,
            $totals->onHold,
            array_map(WarehouseStockResource::fromStock(...), $stock->warehouses),
        );
    }
}
