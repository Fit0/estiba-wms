<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\TenantId;

/**
 * Lets {@see Product::assignGtin()} check that a GTIN belongs to no other product of the
 * tenant (CAT-02) without knowing how products are stored. A unique database constraint
 * backs the check against races.
 */
interface GtinRegistry
{
    public function isGtinTaken(TenantId $tenantId, Gtin $gtin): bool;
}
