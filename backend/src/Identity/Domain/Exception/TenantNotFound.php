<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * There is no tenant with this identifier.
 */
final class TenantNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self(
            'identity.tenant_not_found',
            ['id' => $id],
            \sprintf('No tenant with id %s.', $id),
        );
    }
}
