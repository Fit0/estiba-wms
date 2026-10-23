<?php

declare(strict_types=1);

namespace App\Warehouse\Application\Contract;

use App\Shared\Domain\DomainError;
use App\Shared\Domain\TenantId;

/**
 * How the other contexts find a warehouse (ADR 0003). A warehouse of another tenant is not found.
 */
interface WarehouseDirectory
{
    /**
     * @throws DomainError when the text is not a valid identifier
     */
    public function findById(TenantId $tenantId, string $warehouseId): ?WarehouseSnapshot;
}
