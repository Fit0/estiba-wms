<?php

declare(strict_types=1);

namespace App\Outbound\Application\AllocateOrder;

use App\Inventory\Application\Contract\AllocationRequest;
use App\Inventory\Application\Contract\StockReservations;
use App\Outbound\Application\OrderLookup;
use App\Outbound\Domain\LineAllocation;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderLine;
use App\Outbound\Domain\ReservationId;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;

/**
 * Inventory keeps one reservation per reference, and the reference of a line is the order and the
 * line number. That makes the allocation safe to repeat: the second time it tops the reservation up.
 *
 * A reservation holds stock that nobody else can promise, so it is made only for an order that is
 * still open.
 */
final readonly class AllocateOrderHandler implements CommandHandler
{
    public function __construct(
        private OrderLookup $lookup,
        private StockReservations $reservations,
        private CurrentTenant $tenant,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(AllocateOrder $command): Order
    {
        $order = $this->lookup->get($command->orderId);
        $order->assertNotCancelled();
        $order->assertNotInWave();

        $now = $this->clock->now();
        $allocations = [];

        foreach ($order->lines() as $line) {
            if (!$line->isFullyAllocated()) {
                $allocations[] = $this->allocate($order, $line, $now);
            }
        }

        $order->recordAllocations($allocations, $now);
        $this->events->publish(...$order->pullEvents());

        return $order;
    }

    private function allocate(Order $order, OrderLine $line, \DateTimeImmutable $now): LineAllocation
    {
        $result = $this->reservations->allocate(new AllocationRequest(
            $this->tenant->id(),
            \sprintf('order:%s:%d', $order->id()->value, $line->lineNumber),
            $line->productId->value,
            $order->warehouseId()->value,
            $line->orderedUnits,
            $now,
        ));

        return new LineAllocation($line->lineNumber, ReservationId::fromString($result->reservationId), $result->allocatedUnits);
    }
}
