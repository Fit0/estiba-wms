<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * An email address is not valid.
 *
 * Protects IDN-02.
 */
final class InvalidEmail extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'identity.email_invalid',
            ['value' => $value],
            \sprintf('"%s" is not a valid email address.', $value),
        );
    }
}
