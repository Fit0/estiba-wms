<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\Email;
use App\Shared\Domain\DomainRuleViolation;

/**
 * Another user already signs in with this email address.
 *
 * Protects IDN-01.
 */
final class EmailAlreadyUsed extends DomainRuleViolation
{
    public static function forEmail(Email $email): self
    {
        return new self(
            'identity.email_already_used',
            ['email' => $email->value],
            \sprintf('Another user already uses the email address %s.', $email->value),
        );
    }
}
