<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A reference that already has a reservation asked for another quantity or another product.
 * A reservation is topped up to what was asked at first. It does not change what it serves.
 *
 * Protects STK-11.
 */
final class ReservationRequestChanged extends DomainRuleViolation
{
    public static function forReference(string $reference, int $reservedFor, int $asked): self
    {
        return new self(
            'inventory.reservation_request_changed',
            ['reference' => $reference, 'requested' => $reservedFor, 'asked' => $asked],
            \sprintf('Reservation %s was made for %d units, and %d were asked now.', $reference, $reservedFor, $asked),
        );
    }
}
