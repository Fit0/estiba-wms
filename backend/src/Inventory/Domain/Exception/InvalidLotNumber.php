<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A lot number is empty, longer than 40 characters or has control characters.
 */
final class InvalidLotNumber extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'inventory.lot_number_invalid',
            ['value' => $value],
            'A lot number must have 1 to 40 characters and no control characters.',
        );
    }
}
