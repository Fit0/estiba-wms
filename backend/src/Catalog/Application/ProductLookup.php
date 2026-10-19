<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Exception\ProductNotFound;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductId;
use App\Catalog\Domain\ProductRepository;
use App\Shared\Application\CurrentTenant;

/**
 * Finds a product of the current tenant. A product of another tenant is reported as not found.
 */
final readonly class ProductLookup
{
    public function __construct(
        private ProductRepository $products,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws ProductNotFound
     */
    public function get(string $id): Product
    {
        $product = $this->products->find(ProductId::fromString($id));

        if (!$product instanceof Product || !$product->tenantId()->equals($this->tenant->id())) {
            throw ProductNotFound::forId($id);
        }

        return $product;
    }
}
