<?php

declare(strict_types=1);

namespace App\Outbound\Application\Contract;

use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

/**
 * The goods of an order left the warehouse (OUT-25). It is the event other contexts may listen to (ADR 0003): it
 * carries everything they need in plain values, so nobody has to reach into Outbound to read the shipment.
 */
final readonly class ShipmentLeft implements DomainEvent
{
    /**
     * @param string             $orderReference the reference of the order, the one it was registered with
     * @param int                $parcels        how many parcels left
     * @param list<ShippedUnits> $lines          what left, one entry for each product
     */
    public function __construct(
        public TenantId $tenantId,
        public string $shipmentId,
        public string $orderReference,
        public string $carrier,
        public ?string $trackingNumber,
        public int $parcels,
        public array $lines,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    #[\Override]
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
