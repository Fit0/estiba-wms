<?php

declare(strict_types=1);

namespace App\Outbound\Application\CancelOrder;

use App\Inventory\Application\Contract\StockReservations;
use App\Outbound\Application\OrderLookup;
use App\Outbound\Application\WaveLookup;
use App\Outbound\Domain\Exception\OrderAlreadyInReleasedWave;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\WaveId;
use App\Outbound\Domain\WaveStatus;
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
        private WaveLookup $waves,
        private StockReservations $reservations,
        private CurrentTenant $tenant,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CancelOrder $command): Order
    {
        $order = $this->lookup->get($command->orderId);

        // OUT-07: while the wave is planned the order leaves it; once released its units are on pick lists.
        $waveId = $order->waveId();
        if ($waveId instanceof WaveId) {
            $wave = $this->waves->get($waveId->value);
            if (WaveStatus::Released === $wave->status()) {
                throw OrderAlreadyInReleasedWave::forReference($order->reference());
            }

            $wave->removeOrder($order->id());
        }

        foreach ($order->cancel($this->clock->now()) as $reservationId) {
            $this->reservations->release($this->tenant->id(), $reservationId->value);
        }

        $this->events->publish(...$order->pullEvents());

        return $order;
    }
}
