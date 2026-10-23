<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * More units were allocated to a line than the customer ordered.
 *
 * Protects OUT-03.
 */
final class AllocationExceedsOrdered extends DomainRuleViolation
{
    public static function forLine(int $lineNumber, int $allocated, int $ordered): self
    {
        return new self(
            'outbound.allocation_exceeds_ordered',
            ['line' => $lineNumber, 'allocated' => $allocated, 'ordered' => $ordered],
            \sprintf('Line %d has %d units allocated, and only %d were ordered.', $lineNumber, $allocated, $ordered),
        );
    }
}
