<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A user tried to disable itself or to give up its own admin role.
 *
 * Protects IDN-12.
 */
final class CannotChangeOwnAccess extends DomainRuleViolation
{
    public static function create(): self
    {
        return new self(
            'identity.cannot_change_own_access',
            [],
            'A user cannot disable itself or give up its own admin role.',
        );
    }
}
