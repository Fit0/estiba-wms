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
}
