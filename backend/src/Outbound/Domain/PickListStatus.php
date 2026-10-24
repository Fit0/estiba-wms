<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * Where a pick list is.
 */
enum PickListStatus: string
{
    /** Ready for an operator to pick. */
    case Generated = 'generated';

    /** An operator is walking it. */
    case InProgress = 'in_progress';

    /** Every line is picked or shorted (OUT-13). */
    case Completed = 'completed';

    /** Called off with its wave. */
    case Cancelled = 'cancelled';
}
