<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A name is empty, too long or contains control characters.
 */
final class InvalidDisplayName extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'warehouse.name_invalid',
            ['maxLength' => 100],
            'A name must have 1 to 100 characters and no control characters.',
        );
    }
}
