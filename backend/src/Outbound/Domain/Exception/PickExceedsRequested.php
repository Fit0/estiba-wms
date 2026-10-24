<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * More units were picked than the list asks for.
 *
 * Protects OUT-10.
 */
final class PickExceedsRequested extends DomainRuleViolation
{
    public static function forLine(int $position, int $picked, int $toPick): self
    {
        return new self(
            'outbound.pick_exceeds_requested',
            ['position' => $position, 'picked' => $picked, 'toPick' => $toPick],
            \sprintf('Line %d asks for %d units and %d were picked.', $position, $toPick, $picked),
        );
    }
}
