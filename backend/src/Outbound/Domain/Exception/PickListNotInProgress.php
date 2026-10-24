<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\PickListStatus;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A pick is confirmed on a list that nobody has started, or that is completed or cancelled.
 */
final class PickListNotInProgress extends DomainRuleViolation
{
    public static function forStatus(PickListStatus $status): self
    {
        return new self(
            'outbound.pick_list_not_in_progress',
            ['status' => $status->value],
            \sprintf('Picks are confirmed on a list in progress, and this one is %s.', $status->value),
        );
    }
}
