<?php

declare(strict_types=1);

namespace App\Outbound\Application\DispatchShipment;

use App\Outbound\Domain\Shipment;
use App\Shared\Application\Bus\Command;

/**
 * Hands the parcels of a packed shipment to a carrier. The goods leave the warehouse: the order is
 * shipped and the stock that waited at staging is issued.
 *
 * @implements Command<Shipment>
 */
final readonly class DispatchShipment implements Command
{
    public function __construct(
        public string $shipmentId,
        public string $carrier,
        public ?string $trackingNumber = null,
    ) {
    }
}
