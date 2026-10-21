<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

enum ReceiptStatus: string
{
    /** Goods are being counted at the dock. */
    case Open = 'open';

    /** Counting finished. A closed receipt never changes (INB-09). */
    case Closed = 'closed';
}
