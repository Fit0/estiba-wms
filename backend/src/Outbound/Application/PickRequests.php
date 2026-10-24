<?php

declare(strict_types=1);

namespace App\Outbound\Application;

use App\Inventory\Application\Contract\ReservedStock;
use App\Inventory\Application\Contract\StockReservations;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderLine;
use App\Outbound\Domain\PickRequest;
use App\Outbound\Domain\ReservationId;
use App\Shared\Application\CurrentTenant;
use App\Warehouse\Application\Contract\LocationDirectory;
use App\Warehouse\Application\Contract\LocationSnapshot;

/**
 * Works out what has to be picked for some orders: the stock that was reserved for each line, and
 * where it is. Inventory knows which stock items hold the units and Warehouse knows where each
 * location is, so this asks them and puts the answers together.
 *
 * It picks exactly the units that are reserved, no more and no fewer.
 */
final readonly class PickRequests
{
    public function __construct(
        private StockReservations $reservations,
        private LocationDirectory $locations,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @param list<Order> $orders
     *
     * @return list<PickRequest> in no particular order
     */
    public function of(array $orders): array
    {
        $requests = [];
        $places = [];

        foreach ($orders as $order) {
            foreach ($order->lines() as $line) {
                foreach ($this->heldFor($line) as $held) {
                    $place = $places[$held->locationId] ??= $this->place($held->locationId);

                    $requests[] = new PickRequest(
                        $order->id(),
                        $line->lineNumber,
                        $line->productId,
                        $held->stockItemId,
                        $place->id,
                        $place->code,
                        $place->zonePickSequence,
                        $place->pickSequence,
                        $held->lot,
                        $held->expiryDate,
                        $held->units,
                    );
                }
            }
        }

        return $requests;
    }

    /**
     * @return list<ReservedStock>
     */
    private function heldFor(OrderLine $line): array
    {
        if (!$line->reservationId instanceof ReservationId || 0 === $line->allocatedUnits) {
            return [];
        }

        $held = $this->reservations->stockOf($this->tenant->id(), $line->reservationId->value);

        // The reservation and the line say the same thing, or the order is not what the stock thinks it is.
        if (array_sum(array_map(static fn (ReservedStock $stock): int => $stock->units, $held)) !== $line->allocatedUnits) {
            throw new \LogicException(\sprintf('Line %d has %d units allocated and its reservation holds a different number.', $line->lineNumber, $line->allocatedUnits));
        }

        return $held;
    }

    private function place(string $locationId): LocationSnapshot
    {
        return $this->locations->findById($this->tenant->id(), $locationId)
            ?? throw new \LogicException(\sprintf('Location %s holds reserved stock but does not exist.', $locationId));
    }
}
