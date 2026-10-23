<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * No reservation of the tenant has this identifier.
 */
final class ReservationNotFound extends DomainRuleViolation
{
    public static function withId(string $id): self
    {
        return new self(
            'inventory.reservation_not_found',
            ['id' => $id],
            \sprintf('Reservation %s does not exist.', $id),
        );
    }
}
