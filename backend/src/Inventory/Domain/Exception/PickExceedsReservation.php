<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A pick takes, or gives up, more units than the reservation holds in that stock item.
 *
 * Protects STK-02 and STK-13.
 */
final class PickExceedsReservation extends DomainRuleViolation
{
    public static function of(int $requested, int $held): self
    {
        return new self(
            'inventory.pick_exceeds_reservation',
            ['requested' => $requested, 'held' => $held],
            \sprintf('Cannot pick %d units: the reservation holds %d in that stock item.', $requested, $held),
        );
    }
}
