<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A tracking number is empty, longer than 60 characters or has control characters.
 */
final class InvalidTrackingNumber extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'outbound.tracking_number_invalid',
            [],
            \sprintf('A tracking number must have 1 to 60 characters and no control characters, and "%s" was given.', $value),
        );
    }
}
