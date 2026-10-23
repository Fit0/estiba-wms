<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

/**
 * An active reservation holds units. A released one holds none. Consuming a reservation when the
 * stock is issued at dispatch arrives with the picking (STK-13).
 */
enum ReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
}
