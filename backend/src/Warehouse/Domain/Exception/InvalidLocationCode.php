<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A location code does not follow aisle-module-level.
 *
 * Protects WH-03.
 */
final class InvalidLocationCode extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'warehouse.location_code_invalid',
            ['value' => $value],
            \sprintf('"%s" is not a valid location code. Expected aisle-module-level, for example A-03-02.', $value),
        );
    }
}
