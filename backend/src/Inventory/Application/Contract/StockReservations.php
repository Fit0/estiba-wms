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
     * Records a pick: moves the units taken from the stock item that holds them to another location,
     * as a transfer of the ledger, and keeps them promised to the reservation (STK-08, STK-13). The
     * units not found stop being promised.
     *
     * @throws DomainError when the reservation or the stock item is not the tenant's, or the pick
     *                     takes more than the reservation holds there
     */
    public function pick(StockPick $pick): void;

    /**
     * The goods leave the warehouse with a dispatch: the units the reservation holds are issued from
     * the stock items that hold them, as `issue` movements, and the reservation is consumed (STK-13,
     * STK-16). A reservation that holds nothing, because every unit was short picked, is consumed too.
     *
     * @throws DomainError when the tenant has no such reservation, it was already consumed, or it was released
     */
    public function consume(StockConsumption $consumption): void;

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
