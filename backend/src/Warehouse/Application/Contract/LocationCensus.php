<?php

declare(strict_types=1);

namespace App\Warehouse\Application\Contract;

use App\Shared\Domain\TenantId;

/**
 * How the other contexts count the places where goods are stored (ADR 0003, ADR 0043).
 */
interface LocationCensus
{
    /**
     * The ids of the active locations where stock is kept (reserve and pick face) in all the
     * warehouses of the tenant. Docks, staging, packing and quarantine are not storage.
     *
     * @return list<string>
     */
    public function activeStorageLocationIds(TenantId $tenantId): array;
}
