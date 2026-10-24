<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * Fewer units were picked than the list asks for, and no reason was given.
 *
 * Protects OUT-10.
 */
final class ShortPickReasonRequired extends DomainRuleViolation
{
    public static function forLine(int $position, int $picked, int $toPick): self
    {
        return new self(
            'outbound.short_pick_reason_required',
            ['position' => $position, 'picked' => $picked, 'toPick' => $toPick],
            \sprintf('Line %d asks for %d units and %d were picked, so the reason is required.', $position, $toPick, $picked),
        );
    }
}
