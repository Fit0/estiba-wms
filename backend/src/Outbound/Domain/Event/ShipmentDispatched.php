<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Event;

use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\ShipmentId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

/**
 * The parcels of the order were handed to the carrier. It is the moment the goods leave the warehouse.
 */
final readonly class ShipmentDispatched implements DomainEvent
{
    public function __construct(
        public ShipmentId $shipmentId,
        public TenantId $tenantId,
        public OrderId $orderId,
        public string $carrier,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    #[\Override]
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
