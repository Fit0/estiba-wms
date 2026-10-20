<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A quantity below zero was asked for.
 *
 * Protects STK-01.
 */
final class InvalidQuantity extends DomainRuleViolation
{
    public static function forUnits(int $units): self
    {
        return new self(
            'inventory.quantity_invalid',
            ['units' => $units],
            \sprintf('A quantity cannot be negative, and %d was given.', $units),
        );
    }
}
