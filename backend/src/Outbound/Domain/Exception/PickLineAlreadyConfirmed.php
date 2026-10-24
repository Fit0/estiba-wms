<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A line of the pick list was confirmed twice. Each line is picked once.
 */
final class PickLineAlreadyConfirmed extends DomainRuleViolation
{
    public static function at(int $position): self
    {
        return new self('outbound.pick_line_already_confirmed', ['position' => $position], \sprintf('Line %d of the pick list is already confirmed.', $position));
    }
}
