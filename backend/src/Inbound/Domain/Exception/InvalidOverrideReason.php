<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\OverrideReason;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The reason for putting goods elsewhere is empty, too long or has control characters.
 *
 * Protects INB-13.
 */
final class InvalidOverrideReason extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'inbound.override_reason_invalid',
            ['maxLength' => OverrideReason::MAX_LENGTH],
            \sprintf('The reason must have 1 to %d characters and no control characters, and "%s" was given.', OverrideReason::MAX_LENGTH, $value),
        );
    }
}
