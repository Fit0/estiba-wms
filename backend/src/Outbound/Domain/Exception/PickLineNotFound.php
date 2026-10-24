<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The pick list has no line at this position.
 */
final class PickLineNotFound extends DomainRuleViolation
{
    public static function at(int $position): self
    {
        return new self('outbound.pick_line_not_found', ['position' => $position], \sprintf('The pick list has no line %d.', $position));
    }
}
