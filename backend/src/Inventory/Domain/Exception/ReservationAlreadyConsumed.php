<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ReservationId;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The stock of the reservation was already issued: it left the warehouse with a dispatch.
 *
 * Protects STK-13 and STK-16.
 */
final class ReservationAlreadyConsumed extends DomainRuleViolation
{
    public static function forId(ReservationId $id): self
    {
        return new self(
            'inventory.reservation_already_consumed',
            ['id' => $id->value],
            \sprintf('Reservation %s was already consumed.', $id->value),
        );
    }
}
