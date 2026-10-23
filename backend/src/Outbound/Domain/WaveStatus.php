<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * Where a wave is. Completing it when its pick lists are done arrives with picking (OUT-08).
 */
enum WaveStatus: string
{
    /** Orders are chosen. Nothing has gone to the floor, and orders can still leave. */
    case Planned = 'planned';

    /** On the floor: the pick lists exist, and the orders can no longer change (OUT-07). */
    case Released = 'released';

    /** Called off. Its orders are free again and its pick lists are cancelled. */
    case Cancelled = 'cancelled';
}
