<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Shared\Domain\TenantId;

/**
 * There is no delete method: a reservation that is no longer needed is released, and stays as the
 * record of what was promised.
 */
interface StockReservationRepository
{
    public function find(ReservationId $id): ?StockReservation;

    public function findByReference(TenantId $tenantId, ReservationReference $reference): ?StockReservation;

    public function save(StockReservation $reservation): void;
}
