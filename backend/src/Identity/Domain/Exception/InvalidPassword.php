<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\PlainPassword;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A password does not meet the policy. The password itself is never part of the error.
 *
 * Protects IDN-03.
 */
final class InvalidPassword extends DomainRuleViolation
{
    public static function forPolicy(): self
    {
        return new self(
            'identity.password_invalid',
            ['minLength' => PlainPassword::MIN_LENGTH, 'maxLength' => PlainPassword::MAX_BYTES],
            \sprintf('A password must have %d to %d characters.', PlainPassword::MIN_LENGTH, PlainPassword::MAX_BYTES),
        );
    }
}
