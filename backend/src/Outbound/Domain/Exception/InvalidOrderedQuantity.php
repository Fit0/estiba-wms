<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A line asks for fewer than one unit.
 *
 * Protects OUT-02.
 */
final class InvalidOrderedQuantity extends DomainRuleViolation
{
    public static function forLine(int $lineNumber, int $units): self
    {
        return new self(
            'outbound.ordered_quantity_invalid',
            ['line' => $lineNumber, 'units' => $units],
            \sprintf('Line %d asks for %d units, and an order line needs at least one.', $lineNumber, $units),
        );
    }
}
