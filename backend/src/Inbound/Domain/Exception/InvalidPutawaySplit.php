<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A task was split into nothing, or into all it has. Both halves must keep at least one unit.
 *
 * Protects INB-12 and INB-14.
 */
final class InvalidPutawaySplit extends DomainRuleViolation
{
    public static function of(int $units, int $taskUnits): self
    {
        return new self(
            'inbound.putaway_split_invalid',
            ['units' => $units, 'taskUnits' => $taskUnits],
            \sprintf('A task of %d units can be split by 1 to %d units, and %d were asked for.', $taskUnits, $taskUnits - 1, $units),
        );
    }
}
