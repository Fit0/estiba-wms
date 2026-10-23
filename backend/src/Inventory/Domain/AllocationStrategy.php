<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

/**
 * Which stock is promised first (STK-12).
 *
 * FEFO, first expired first out, is for products tracked by expiry date: the stock that expires
 * first goes first, then the oldest arrival. FIFO, first in first out, is for the rest: the stock
 * that arrived first goes first.
 */
enum AllocationStrategy: string
{
    case Fefo = 'fefo';
    case Fifo = 'fifo';
}
