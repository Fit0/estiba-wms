<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * Where an order is on its way out. Waves, picking and shipping add their own steps later.
 */
enum OrderStatus: string
{
    /** Registered. Nothing is reserved for it yet. */
    case Registered = 'registered';

    /** Some of the ordered units are reserved, and the rest wait for stock. */
    case PartiallyAllocated = 'partially_allocated';

    /** Every ordered unit is reserved. */
    case Allocated = 'allocated';

    /** Released to the floor with a wave, or waiting for it to be released (OUT-05). */
    case InWave = 'in_wave';

    /** The customer will not receive it. Its reservations are released (OUT-04). */
    case Cancelled = 'cancelled';
}
