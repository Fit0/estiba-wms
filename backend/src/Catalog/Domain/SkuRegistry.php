<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\TenantId;

/**
 * Lets {@see Product::register()} check that a SKU is free (CAT-01) without knowing how
 * products are stored. A unique database constraint backs the check against races.
 */
interface SkuRegistry
{
    public function isSkuTaken(TenantId $tenantId, Sku $sku): bool;
}
