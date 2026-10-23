<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A change would take units that are promised to an order: the reserved units of a stock item
 * never exceed what it holds.
 *
 * Protects STK-02 and STK-10.
 */
final class NotEnoughAvailableStock extends DomainRuleViolation
{
    public static function reserving(int $requested, int $available): self
    {
        return new self(
            'inventory.not_enough_available_stock',
            ['requested' => $requested, 'available' => $available],
            \sprintf('Cannot reserve %d units from a stock item with %d available.', $requested, $available),
        );
    }

    public static function taking(int $requested, int $available): self
    {
        return new self(
            'inventory.not_enough_available_stock',
            ['requested' => $requested, 'available' => $available],
            \sprintf('Cannot take %d units: only %d are not reserved.', $requested, $available),
        );
    }
}
