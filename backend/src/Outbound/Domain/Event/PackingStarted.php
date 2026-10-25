<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Event;

use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\ShipmentId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

/**
 * The packer began to put the picked units of an order into parcels.
 */
final readonly class PackingStarted implements DomainEvent
{
    public function __construct(
        public ShipmentId $shipmentId,
        public TenantId $tenantId,
        public OrderId $orderId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    #[\Override]
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
