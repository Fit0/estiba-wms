<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

/**
 * What can be done with the units of a stock item. Only available stock can be allocated.
 * Changing the status of units is a transfer between two stock items.
 */
enum StockStatus: string
{
    case Available = 'available';
    case Quarantine = 'quarantine';
    case Damaged = 'damaged';
    case OnHold = 'on_hold';
}
