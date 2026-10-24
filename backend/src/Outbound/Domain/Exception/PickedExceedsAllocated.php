<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * More units were picked for an order line than were allocated to it.
 *
 * Protects OUT-03.
 */
final class PickedExceedsAllocated extends DomainRuleViolation
{
    public static function forLine(int $lineNumber, int $picked, int $allocated): self
    {
        return new self(
            'outbound.picked_exceeds_allocated',
            ['line' => $lineNumber, 'picked' => $picked, 'allocated' => $allocated],
            \sprintf('Line %d has %d units picked and only %d allocated.', $lineNumber, $picked, $allocated),
        );
    }
}
