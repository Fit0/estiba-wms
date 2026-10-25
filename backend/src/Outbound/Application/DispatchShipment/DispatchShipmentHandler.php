<?php

declare(strict_types=1);

namespace App\Outbound\Application\DispatchShipment;

use App\Inventory\Application\Contract\StockConsumption;
use App\Inventory\Application\Contract\StockReservations;
use App\Outbound\Application\OrderLookup;
use App\Outbound\Application\ShipmentLookup;
use App\Outbound\Domain\CarrierName;
use App\Outbound\Domain\Shipment;
use App\Outbound\Domain\TrackingNumber;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Application\CurrentUser;

/**
 * The shipment, the order and the stock change in one transaction, so goods are never dispatched
 * while their stock is still on the books, nor issued for a shipment that did not go (OUT-25). The
 * stock is issued from where it waits, which is the staging location the picks took it to (ADR 0020).
 */
final readonly class DispatchShipmentHandler implements CommandHandler
{
    public function __construct(
        private ShipmentLookup $shipments,
        private OrderLookup $orders,
        private StockReservations $stock,
        private CurrentTenant $tenant,
        private CurrentUser $user,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(DispatchShipment $command): Shipment
    {
        $shipment = $this->shipments->get($command->shipmentId);
        $order = $this->orders->get($shipment->orderId()->value);
        $at = $this->clock->now();

        $shipment->dispatch(
            CarrierName::fromString($command->carrier),
            null === $command->trackingNumber ? null : TrackingNumber::fromString($command->trackingNumber),
            $at,
        );

        $author = $this->user->id();
        foreach ($order->recordShipment($at) as $reservationId) {
            $this->stock->consume(new StockConsumption($this->tenant->id(), $reservationId->value, 'shipment:'.$shipment->id()->value, $author, $at));
        }

        $this->events->publish(...$shipment->pullEvents(), ...$order->pullEvents());

        return $shipment;
    }
}
