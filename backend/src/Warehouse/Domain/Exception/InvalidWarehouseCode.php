<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A warehouse code does not follow the format.
 *
 * Protects WH-01.
 */
final class InvalidWarehouseCode extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'warehouse.warehouse_code_invalid',
            ['value' => $value],
            \sprintf('"%s" is not a valid warehouse code. Use 2 to 12 letters or digits, with single hyphens between groups.', $value),
        );
    }
}
