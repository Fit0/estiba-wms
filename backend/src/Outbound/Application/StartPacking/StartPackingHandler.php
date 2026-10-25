<?php

declare(strict_types=1);

namespace App\Outbound\Application\StartPacking;

use App\Outbound\Application\OrderLookup;
use App\Outbound\Domain\Exception\OrderNotFound;
use App\Outbound\Domain\Exception\PackingAlreadyStarted;
use App\Outbound\Domain\Shipment;
use App\Outbound\Domain\ShipmentId;
use App\Outbound\Domain\ShipmentRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Application\IdGenerator;

final readonly class StartPackingHandler implements CommandHandler
{
    public function __construct(
        private OrderLookup $orders,
        private ShipmentRepository $shipments,
        private CurrentTenant $tenant,
        private EventBus $events,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    /**
     * @throws OrderNotFound
     * @throws PackingAlreadyStarted OUT-20
     */
    public function __invoke(StartPacking $command): Shipment
    {
        $order = $this->orders->get($command->orderId);

        // OUT-20: a unique index on the order backs this check against two packers starting at once.
        if ($this->shipments->findByOrder($order->id()) instanceof Shipment) {
            throw PackingAlreadyStarted::forOrder($order->reference());
        }

        $shipment = Shipment::start(ShipmentId::fromString($this->ids->next()), $this->tenant->id(), $order, $this->clock->now());

        $this->shipments->save($shipment);
        $this->events->publish(...$shipment->pullEvents());

        return $shipment;
    }
}
