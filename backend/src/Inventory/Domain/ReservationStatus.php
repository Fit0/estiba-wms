<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

/**
 * An active reservation holds units. A released one gave them back, and a consumed one saw them leave
 * the warehouse when the order was dispatched (STK-13, STK-16). Neither holds any.
 */
enum ReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Consumed = 'consumed';
}
