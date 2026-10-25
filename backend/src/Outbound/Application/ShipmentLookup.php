<?php

declare(strict_types=1);

namespace App\Outbound\Application;

use App\Outbound\Domain\Exception\ShipmentNotFound;
use App\Outbound\Domain\Shipment;
use App\Outbound\Domain\ShipmentId;
use App\Outbound\Domain\ShipmentRepository;
use App\Shared\Application\CurrentTenant;

/**
 * Finds a shipment of the current tenant. A shipment of another tenant is reported as not found.
 */
final readonly class ShipmentLookup
{
    public function __construct(
        private ShipmentRepository $shipments,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws ShipmentNotFound
     */
    public function get(string $id): Shipment
    {
        $shipment = $this->shipments->find(ShipmentId::fromString($id));

        if (!$shipment instanceof Shipment || !$shipment->tenantId()->equals($this->tenant->id())) {
            throw ShipmentNotFound::forId($id);
        }

        return $shipment;
    }
}
