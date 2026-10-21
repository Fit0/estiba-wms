<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

/**
 * The state goods arrive in, as the operator sees it at the dock.
 */
enum ItemCondition: string
{
    case Good = 'good';

    /** A discrepancy. The units enter stock in quarantine, never available (INB-10). */
    case Damaged = 'damaged';
}
