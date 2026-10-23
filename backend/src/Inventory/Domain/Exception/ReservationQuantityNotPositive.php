<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A reservation was asked for zero units.
 *
 * Protects STK-11.
 */
final class ReservationQuantityNotPositive extends DomainRuleViolation
{
    public static function ofZero(): self
    {
        return new self(
            'inventory.reservation_quantity_not_positive',
            [],
            'A reservation must ask for at least one unit.',
        );
    }
}
