<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A role name is not one of the known roles.
 */
final class InvalidRole extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'identity.role_invalid',
            ['value' => $value],
            \sprintf('"%s" is not a role. Use admin, supervisor or operator.', $value),
        );
    }
}
