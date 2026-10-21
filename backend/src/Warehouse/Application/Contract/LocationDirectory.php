<?php

declare(strict_types=1);

namespace App\Warehouse\Application\Contract;

use App\Shared\Domain\DomainError;
use App\Shared\Domain\TenantId;

/**
 * How the other contexts find a location (ADR 0003). A location of another tenant is not found.
 */
interface LocationDirectory
{
    /**
     * @throws DomainError when the text is not a valid identifier
     */
    public function findById(TenantId $tenantId, string $locationId): ?LocationSnapshot;

    /**
     * The active locations of a warehouse of these types, in the order of the picking route: by the
     * pick sequence of their zone, then their own, then their code.
     *
     * @param list<string> $types values of the location type, such as `reserve`
     *
     * @return list<LocationSnapshot>
     *
     * @throws DomainError when the warehouse identifier is not valid
     */
    public function activeLocationsOf(TenantId $tenantId, string $warehouseId, array $types): array;
}
