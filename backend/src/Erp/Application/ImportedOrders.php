<?php

declare(strict_types=1);

namespace App\Erp\Application;

use App\Shared\Domain\TenantId;

/**
 * The orders that came from the ERP, by the number it gave them. Only those are confirmed back: an order that a
 * person typed into the warehouse is not in the ERP, and the ERP would refuse to hear about it (ADR 0049).
 */
interface ImportedOrders
{
    /**
     * Writing down an order that is already there changes nothing.
     */
    public function remember(TenantId $tenantId, string $reference, \DateTimeImmutable $at): void;

    public function has(TenantId $tenantId, string $reference): bool;
}
