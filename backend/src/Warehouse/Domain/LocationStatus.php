<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

enum LocationStatus: string
{
    case Active = 'active';

    /** Receives no putaways and no allocations. Stock already inside stays (WH-06). */
    case Blocked = 'blocked';
}
