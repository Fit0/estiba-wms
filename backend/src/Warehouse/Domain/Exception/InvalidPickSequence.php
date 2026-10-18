<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A pick sequence is negative.
 */
final class InvalidPickSequence extends DomainRuleViolation
{
    public static function forValue(int $value): self
    {
        return new self(
            'warehouse.pick_sequence_invalid',
            ['value' => $value],
            \sprintf('A pick sequence cannot be negative, got %d.', $value),
        );
    }
}
