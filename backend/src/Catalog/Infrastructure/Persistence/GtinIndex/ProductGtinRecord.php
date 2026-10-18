<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\GtinIndex;

/**
 * One row per GTIN of a product. A unique index on the tenant and the GTIN padded to 14 digits
 * enforces CAT-02 across products and against races, which a JSON column cannot do.
 * It is a persistence detail, so it lives here and not in the domain.
 */
final readonly class ProductGtinRecord
{
    public function __construct(
        public string $tenantId,
        public string $gtin,
        public string $productId,
    ) {
    }
}
