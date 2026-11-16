<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The change would leave the tenant without an active administrator.
 *
 * Protects IDN-11.
 */
final class LastAdminRequired extends DomainRuleViolation
{
    public static function create(): self
    {
        return new self(
            'identity.last_admin_required',
            [],
            'A tenant needs at least one active administrator.',
        );
    }
}
