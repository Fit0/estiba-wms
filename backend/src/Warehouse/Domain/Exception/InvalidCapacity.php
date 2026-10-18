<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A capacity value is not a positive whole number.
 *
 * Protects WH-04.
 */
final class InvalidCapacity extends DomainRuleViolation
{
    public static function forField(string $field, int $value): self
    {
        return new self(
            'warehouse.capacity_invalid',
            ['field' => $field, 'value' => $value],
            \sprintf('%s must be a positive whole number, got %d.', $field, $value),
        );
    }
}
