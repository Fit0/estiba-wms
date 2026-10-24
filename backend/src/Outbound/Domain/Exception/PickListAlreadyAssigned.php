<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OperatorId;
use App\Shared\Domain\DomainRuleViolation;

/**
 * Someone other than the assigned operator tried to work on a pick list.
 *
 * Protects OUT-12.
 */
final class PickListAlreadyAssigned extends DomainRuleViolation
{
    public static function to(OperatorId $operator): self
    {
        return new self(
            'outbound.pick_list_already_assigned',
            ['operatorId' => $operator->value],
            \sprintf('The pick list is assigned to operator %s.', $operator->value),
        );
    }
}
