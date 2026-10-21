<?php

declare(strict_types=1);

namespace App\Catalog\Application\Contract;

use App\Shared\Domain\DomainError;
use App\Shared\Domain\TenantId;

/**
 * How the other contexts find a product (ADR 0003). A product of another tenant is not found.
 */
interface ProductCatalog
{
    /**
     * @throws DomainError when the text is not a valid identifier
     */
    public function findById(TenantId $tenantId, string $productId): ?ProductSnapshot;

    /**
     * The product a scanned barcode belongs to. GTIN-8, 12, 13 and 14 of the same item find the
     * same product.
     *
     * @throws DomainError when the text is not a valid GTIN
     */
    public function findByGtin(TenantId $tenantId, string $gtin): ?ProductSnapshot;
}
