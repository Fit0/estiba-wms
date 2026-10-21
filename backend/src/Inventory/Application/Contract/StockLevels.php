<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

use App\Shared\Domain\DomainError;
use App\Shared\Domain\TenantId;

/**
 * How the other contexts learn what stock a location holds (ADR 0003).
 */
interface StockLevels
{
    /**
     * The products each of these locations holds, one entry per location and product. A location
     * that holds nothing has no entry.
     *
     * @param list<string> $locationIds
     *
     * @return list<StockAtLocation>
     *
     * @throws DomainError when an identifier is not valid
     */
    public function at(TenantId $tenantId, array $locationIds): array;
}
