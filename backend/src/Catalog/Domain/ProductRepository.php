<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * There is no delete method on purpose: a product that leaves the range is discontinued,
 * because stock history and orders keep pointing at it (CAT-08).
 */
interface ProductRepository extends SkuRegistry, GtinRegistry
{
    public function find(ProductId $id): ?Product;

    public function save(Product $product): void;
}
