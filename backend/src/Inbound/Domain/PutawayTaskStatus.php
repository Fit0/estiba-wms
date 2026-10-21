<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

enum PutawayTaskStatus: string
{
    /** The goods wait at the dock. */
    case Pending = 'pending';

    /** The goods are in their location and the stock moved with them. */
    case Confirmed = 'confirmed';
}
