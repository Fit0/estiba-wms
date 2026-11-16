<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * There is no user with this email address or id.
 */
final class UserNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self(
            'identity.user_id_not_found',
            ['id' => $id],
            \sprintf('No user with id %s.', $id),
        );
    }

    public static function forEmail(string $email): self
    {
        return new self(
            'identity.user_not_found',
            ['email' => $email],
            \sprintf('No user with email %s.', $email),
        );
    }
}
