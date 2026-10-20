<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A tenant name is empty, too long or contains control characters.
 *
 * Protects IDN-07.
 */
final class InvalidTenantName extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'identity.tenant_name_invalid',
            ['maxLength' => 100],
            'A tenant name must have 1 to 100 characters and no control characters.',
        );
    }
}
