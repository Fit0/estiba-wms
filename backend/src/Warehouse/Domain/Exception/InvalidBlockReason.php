<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A block reason is empty, too long or contains control characters.
 */
final class InvalidBlockReason extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'warehouse.block_reason_invalid',
            ['maxLength' => 200],
            'A block reason must have 1 to 200 characters and no control characters.',
        );
    }
}
