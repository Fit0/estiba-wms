<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A count at the dock of zero or fewer units.
 */
final class InvalidReceivedQuantity extends DomainRuleViolation
{
    public static function forUnits(int $units): self
    {
        return new self(
            'inbound.received_quantity_invalid',
            ['units' => $units],
            \sprintf('A received item must have at least one unit, and %d were given.', $units),
        );
    }
}
