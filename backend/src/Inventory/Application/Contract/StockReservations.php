<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

use App\Shared\Domain\DomainError;
use App\Shared\Domain\TenantId;

/**
 * How the other contexts promise stock to a demand (ADR 0003, ADR 0014). It reserves units in the
 * caller's transaction and moves none: reserved units stay where they are until they are picked.
 */
interface StockReservations
{
    /**
     * Reserves the units from the stock that is available, in an active storage location of the
     * warehouse: the stock that expires first for products tracked by expiry date, and the oldest
     * arrival for the rest (STK-12). It covers as much as there is.
     *
     * @throws DomainError when the product cannot be reserved, or the same reference asked for something else
     */
    public function allocate(AllocationRequest $request): AllocationResult;

    /**
     * Gives the units of a reservation back to the stock. Releasing a reservation twice is the same
     * as releasing it once (STK-14).
     *
     * @throws DomainError when the tenant has no such reservation
     */
    public function release(TenantId $tenantId, string $reservationId): void;

    /**
     * The stock items that hold the units of a reservation, with where each one is and its lot and
     * expiry date, in no particular order. A released reservation holds nothing.
     *
     * @return list<ReservedStock>
     *
     * @throws DomainError when the tenant has no such reservation
     */
    public function stockOf(TenantId $tenantId, string $reservationId): array;
}
