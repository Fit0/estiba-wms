<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * How fast a product leaves the warehouse, in the classic ABC split: A for the few products that
 * make most of the movements, C for the many that rarely move. Catalog gives each product its
 * class, and putaway in Inbound keeps the fast movers close to the start of the picking route.
 *
 * Set by hand for now. It can be worked out from the shipments once there are some (E07-04).
 */
enum TurnoverClass: string
{
    public const self DEFAULT = self::B;

    case A = 'A';
    case B = 'B';
    case C = 'C';
}
