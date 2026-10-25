<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Event;

use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\ShipmentId;
use App\Outbound\Domain\Sscc;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

/**
 * A parcel was closed and its SSCC assigned, so its label can be printed.
 */
final readonly class ParcelClosed implements DomainEvent
{
    public function __construct(
        public ShipmentId $shipmentId,
        public TenantId $tenantId,
        public OrderId $orderId,
        public Sscc $sscc,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    #[\Override]
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
