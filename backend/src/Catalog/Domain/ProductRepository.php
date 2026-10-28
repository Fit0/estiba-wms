<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;

/**
 * There is no delete method on purpose: a product that leaves the range is discontinued,
 * because stock history and orders keep pointing at it (CAT-08).
 */
interface ProductRepository extends SkuRegistry, GtinRegistry
{
    public function find(ProductId $id): ?Product;

    /**
     * The product of the tenant that carries this GTIN, whatever length it was printed with.
     */
    public function findByGtin(TenantId $tenantId, Gtin $gtin): ?Product;

    /**
     * The tenant's products, ordered by SKU, optionally narrowed to SKUs that start with a
     * text, to one status and to those whose SKU or name contains a text. Case does not
     * matter and `%` or `_` in a text are plain characters.
     *
     * @return Page<Product>
     */
    public function search(TenantId $tenantId, ?string $skuPrefix, ?ProductStatus $status, ?string $text, PageRequest $page): Page;

    public function save(Product $product): void;
}
