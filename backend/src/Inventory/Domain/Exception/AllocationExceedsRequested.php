<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The stock items chosen to cover a reservation hold more units than it asked for.
 *
 * Protects STK-11.
 */
final class AllocationExceedsRequested extends DomainRuleViolation
{
    public static function of(int $allocated, int $requested): self
    {
        return new self(
            'inventory.allocation_exceeds_requested',
            ['allocated' => $allocated, 'requested' => $requested],
            \sprintf('%d units were allocated for a reservation of %d.', $allocated, $requested),
        );
    }
}
