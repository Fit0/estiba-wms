<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

use App\Shared\Domain\TenantId;

/**
 * Lets {@see Warehouse::create()} check that a code is free (WH-01) without knowing how
 * warehouses are stored. A unique database constraint backs the check against races.
 */
interface WarehouseCodeRegistry
{
    public function isTaken(TenantId $tenantId, WarehouseCode $code): bool;
}
