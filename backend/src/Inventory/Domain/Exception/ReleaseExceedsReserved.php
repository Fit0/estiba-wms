<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * More units were released than the stock item has reserved.
 *
 * Protects STK-02.
 */
final class ReleaseExceedsReserved extends DomainRuleViolation
{
    public static function releasing(int $requested, int $reserved): self
    {
        return new self(
            'inventory.release_exceeds_reserved',
            ['requested' => $requested, 'reserved' => $reserved],
            \sprintf('Cannot release %d units from a stock item with %d reserved.', $requested, $reserved),
        );
    }
}
