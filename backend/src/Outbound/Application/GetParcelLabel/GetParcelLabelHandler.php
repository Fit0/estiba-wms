<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetParcelLabel;

use App\Outbound\Application\OrderLookup;
use App\Outbound\Application\ShipmentLookup;
use App\Outbound\Domain\Exception\InvalidSscc;
use App\Outbound\Domain\Exception\ParcelNotFound;
use App\Outbound\Domain\Exception\ShipmentNotFound;
use App\Outbound\Domain\Sscc;
use App\Shared\Application\Bus\QueryHandler;

final readonly class GetParcelLabelHandler implements QueryHandler
{
    public function __construct(
        private ShipmentLookup $shipments,
        private OrderLookup $orders,
    ) {
    }

    /**
     * @throws ShipmentNotFound
     * @throws ParcelNotFound
     * @throws InvalidSscc
     */
    public function __invoke(GetParcelLabel $query): ParcelLabel
    {
        $shipment = $this->shipments->get($query->shipmentId);
        $sscc = Sscc::fromString($query->sscc);
        $parcel = $shipment->parcel($sscc);
        $order = $this->orders->get($shipment->orderId()->value);
        $address = $order->shipTo();

        return new ParcelLabel(
            $parcel->sscc->value,
            $order->reference()->value,
            $order->customer()->value,
            $address->street,
            $address->postalCode,
            $address->city,
            $address->countryCode,
            $shipment->parcelNumber($sscc),
            $parcel->weightGrams,
            $parcel->totalUnits(),
            $parcel->closedAt,
        );
    }
}
