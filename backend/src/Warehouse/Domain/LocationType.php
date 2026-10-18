<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

enum LocationType: string
{
    /** Bulk storage, usually pallets on racks. */
    case Reserve = 'reserve';

    /** A picking position, where operators take units. */
    case PickFace = 'pick_face';

    /** Where goods arrive or leave. */
    case Dock = 'dock';

    /** A waiting area between steps. */
    case Staging = 'staging';

    case Packing = 'packing';

    /** Goods held apart until someone decides what to do with them. */
    case Quarantine = 'quarantine';
}
