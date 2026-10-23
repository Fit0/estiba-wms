<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * Where a pick list is. An operator starting it and completing it arrive with picking.
 */
enum PickListStatus: string
{
    /** Ready for an operator to pick. */
    case Generated = 'generated';

    /** Called off with its wave. */
    case Cancelled = 'cancelled';
}
