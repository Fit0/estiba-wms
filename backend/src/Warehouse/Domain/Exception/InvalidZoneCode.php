<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A zone code does not follow the format.
 *
 * Protects WH-02.
 */
final class InvalidZoneCode extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'warehouse.zone_code_invalid',
            ['value' => $value],
            \sprintf('"%s" is not a valid zone code. Use 1 to 10 letters or digits.', $value),
        );
    }
}
