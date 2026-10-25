<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetShipment;

use App\Outbound\Application\ShipmentLookup;
use App\Outbound\Domain\Exception\ShipmentNotFound;
use App\Outbound\Domain\Shipment;
use App\Shared\Application\Bus\QueryHandler;

final readonly class GetShipmentHandler implements QueryHandler
{
    public function __construct(private ShipmentLookup $lookup)
    {
    }

    /**
     * @throws ShipmentNotFound
     */
    public function __invoke(GetShipment $query): Shipment
    {
        return $this->lookup->get($query->shipmentId);
    }
}
