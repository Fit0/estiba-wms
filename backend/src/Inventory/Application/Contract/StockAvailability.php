<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

use App\Shared\Domain\TenantId;

/**
 * How the other contexts learn why stock that is in the warehouse cannot be promised (ADR 0058).
 * Allocation says how many units it could not cover, and this says where the rest of the product is and
 * what keeps it from being used. It reads and changes nothing.
 */
interface StockAvailability
{
    /**
     * The units of the product that no allocation can use, by reason, as they are right now. Units
     * that the reservation of `$reference` already holds are not counted as held by others.
     *
     * @return list<UnreservableStock> in the order of {@see UnreservableReason}, only the reasons that apply
     */
    public function unreservable(TenantId $tenantId, string $productId, string $warehouseId, string $reference, \DateTimeImmutable $at): array;
}
