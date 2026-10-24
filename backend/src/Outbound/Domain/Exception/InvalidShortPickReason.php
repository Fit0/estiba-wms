<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The reason of a short pick is empty, longer than 200 characters or has control characters.
 */
final class InvalidShortPickReason extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'outbound.short_pick_reason_invalid',
            [],
            \sprintf('A short pick reason must have 1 to 200 characters and no control characters, and "%s" was given.', $value),
        );
    }
}
