<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

enum AsnStatus: string
{
    /** Registered. Nothing has been received against it yet. */
    case Expected = 'expected';

    /** At least one receipt closed, and some lines still wait for units. */
    case PartiallyReceived = 'partially_received';

    /** Every line got at least the units it expected. */
    case Received = 'received';

    /** Finished, possibly with a shortage. Nothing more can be received against it (INB-04). */
    case Closed = 'closed';

    /** Will not arrive. Nothing can be received against it (INB-04). */
    case Cancelled = 'cancelled';

    /**
     * INB-04: a closed or cancelled ASN cannot be received against.
     */
    public function acceptsReceipts(): bool
    {
        return self::Closed !== $this && self::Cancelled !== $this;
    }
}
