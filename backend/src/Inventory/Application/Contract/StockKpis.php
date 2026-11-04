<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

use App\Shared\Domain\DomainError;
use App\Shared\Domain\TenantId;

/**
 * The figures of stock that other contexts show on a dashboard (ADR 0003, ADR 0043). They are read
 * from the stock overview, so they cost one query each and never load a stock item.
 */
interface StockKpis
{
    public function summary(TenantId $tenantId): StockSummary;

    /**
     * How many of these locations hold at least one unit.
     *
     * @param list<string> $locationIds
     *
     * @throws DomainError when an identifier is not valid
     */
    public function occupiedAmong(TenantId $tenantId, array $locationIds): int;
}
