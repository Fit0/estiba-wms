<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * What the stock gave to one line of an order: the reservation, and the units it holds now.
 */
final readonly class LineAllocation
{
    public function __construct(
        public int $lineNumber,
        public ReservationId $reservationId,
        public int $allocatedUnits,
    ) {
    }
}
