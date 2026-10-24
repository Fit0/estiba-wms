<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * Where a wave is.
 */
enum WaveStatus: string
{
    /** Orders are chosen. Nothing has gone to the floor, and orders can still leave. */
    case Planned = 'planned';

    /** On the floor: the pick lists exist, and the orders can no longer change (OUT-07). */
    case Released = 'released';

    /** Every pick list is completed or cancelled, and one at least is completed (OUT-08). */
    case Completed = 'completed';

    /** Called off. Its orders are free again and its pick lists are cancelled. */
    case Cancelled = 'cancelled';
}
