<?php

declare(strict_types=1);

namespace App\Erp\Application;

use App\Shared\Domain\TenantId;

/**
 * A confirmation that waits in the outbox to be sent to the ERP.
 */
final readonly class OutboxItem
{
    /**
     * @param int $attempts how many times it was tried and the ERP could not take it
     */
    public function __construct(
        public string $shipmentId,
        public TenantId $tenantId,
        public ShipmentConfirmation $confirmation,
        public int $attempts,
    ) {
    }
}
