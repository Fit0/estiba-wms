<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetOrderShortages;

use App\Inventory\Application\Contract\StockAvailability;
use App\Outbound\Application\LineReservationReference;
use App\Outbound\Application\OrderLookup;
use App\Outbound\Domain\Exception\OrderNotFound;
use App\Outbound\Domain\OrderStatus;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;

/**
 * Asks Inventory about each line that is not fully reserved. Nothing is stored: the answer is how the
 * stock is now, so it stops mentioning a dock as soon as the goods are put away.
 */
final readonly class GetOrderShortagesHandler implements QueryHandler
{
    public function __construct(
        private OrderLookup $lookup,
        private StockAvailability $availability,
        private CurrentTenant $tenant,
        private Clock $clock,
    ) {
    }

    /**
     * @return list<LineShortage>
     *
     * @throws OrderNotFound
     */
    public function __invoke(GetOrderShortages $query): array
    {
        $order = $this->lookup->get($query->orderId);
        if (OrderStatus::Cancelled === $order->status()) {
            return [];
        }

        $now = $this->clock->now();
        $shortages = [];

        foreach ($order->lines() as $line) {
            if ($line->isFullyAllocated()) {
                continue;
            }

            $shortages[] = new LineShortage(
                $line->lineNumber,
                $line->productId->value,
                $line->orderedUnits,
                $line->allocatedUnits,
                $line->shortageUnits(),
                $this->availability->unreservable(
                    $this->tenant->id(),
                    $line->productId->value,
                    $order->warehouseId()->value,
                    LineReservationReference::of($order, $line),
                    $now,
                ),
            );
        }

        return $shortages;
    }
}
