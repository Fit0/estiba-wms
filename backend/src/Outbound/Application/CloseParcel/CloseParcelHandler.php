<?php

declare(strict_types=1);

namespace App\Outbound\Application\CloseParcel;

use App\Outbound\Application\ShipmentLookup;
use App\Outbound\Domain\ParcelContent;
use App\Outbound\Domain\ProductId;
use App\Outbound\Domain\Shipment;
use App\Outbound\Domain\ShipmentRepository;
use App\Outbound\Domain\SsccSequence;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;

/**
 * The SSCC is taken in the same transaction that closes the parcel, so a parcel that is refused
 * does not use one up (OUT-19).
 */
final readonly class CloseParcelHandler implements CommandHandler
{
    public function __construct(
        private ShipmentLookup $lookup,
        private ShipmentRepository $shipments,
        private SsccSequence $sequence,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CloseParcel $command): Shipment
    {
        $shipment = $this->lookup->get($command->shipmentId);

        $shipment->closeParcel(
            $this->sequence->next(),
            array_map(static fn (CloseParcelContent $content): ParcelContent => new ParcelContent(ProductId::fromString($content->productId), $content->units), $command->contents),
            $command->weightGrams,
            $this->clock->now(),
        );

        $this->shipments->save($shipment);
        $this->events->publish(...$shipment->pullEvents());

        return $shipment;
    }
}
