<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ReservationId;
use App\Shared\Domain\DomainRuleViolation;

/**
 * Units were added to a reservation that was already released.
 *
 * Protects STK-13 and STK-14.
 */
final class ReservationNotActive extends DomainRuleViolation
{
    public static function forId(ReservationId $id): self
    {
        return new self(
            'inventory.reservation_not_active',
            ['id' => $id->value],
            \sprintf('Reservation %s is not active, so it cannot take more units.', $id->value),
        );
    }
}
