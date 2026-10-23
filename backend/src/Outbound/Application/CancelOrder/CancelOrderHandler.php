<?php

declare(strict_types=1);

namespace App\Outbound\Application\CancelOrder;

use App\Inventory\Application\Contract\StockReservations;
use App\Outbound\Application\OrderLookup;
use App\Outbound\Domain\Order;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;

/**
 * The order and its reservations change in the same transaction, so an order is never cancelled
 * while it still holds stock (OUT-04). Releasing is safe to repeat in Inventory, so a retried
 * command does no harm.
 */
final readonly class CancelOrderHandler implements CommandHandler
{
    public function __construct(
        private OrderLookup $lookup,
        private StockReservations $reservations,
        private CurrentTenant $tenant,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CancelOrder $command): Order
    {
        $order = $this->lookup->get($command->orderId);

        foreach ($order->cancel($this->clock->now()) as $reservationId) {
            $this->reservations->release($this->tenant->id(), $reservationId->value);
        }

        $this->events->publish(...$order->pullEvents());

        return $order;
    }
}
