<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The reference of a reservation is empty, longer than 80 characters or has control characters.
 */
final class InvalidReservationReference extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'inventory.reservation_reference_invalid',
            ['value' => $value],
            'A reservation reference must have 1 to 80 characters and no control characters.',
        );
    }
}
