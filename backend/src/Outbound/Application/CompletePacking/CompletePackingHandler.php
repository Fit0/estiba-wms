<?php

declare(strict_types=1);

namespace App\Outbound\Application\CompletePacking;

use App\Outbound\Application\ShipmentLookup;
use App\Outbound\Domain\Shipment;
use App\Outbound\Domain\ShipmentRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;

final readonly class CompletePackingHandler implements CommandHandler
{
    public function __construct(
        private ShipmentLookup $lookup,
        private ShipmentRepository $shipments,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CompletePacking $command): Shipment
    {
        $shipment = $this->lookup->get($command->shipmentId);
        $shipment->completePacking($this->clock->now());

        $this->shipments->save($shipment);
        $this->events->publish(...$shipment->pullEvents());

        return $shipment;
    }
}
