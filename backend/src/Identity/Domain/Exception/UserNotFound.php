<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * There is no user with this email address.
 */
final class UserNotFound extends DomainRuleViolation
{
    public static function forEmail(string $email): self
    {
        return new self(
            'identity.user_not_found',
            ['email' => $email],
            \sprintf('No user with email %s.', $email),
        );
    }
}
