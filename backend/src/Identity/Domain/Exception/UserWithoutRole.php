<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A user was given no role.
 *
 * Protects IDN-04.
 */
final class UserWithoutRole extends DomainRuleViolation
{
    public static function none(): self
    {
        return new self(
            'identity.roles_required',
            [],
            'A user needs at least one role.',
        );
    }
}
