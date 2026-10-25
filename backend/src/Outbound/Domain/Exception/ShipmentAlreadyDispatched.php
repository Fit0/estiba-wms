<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The shipment has already gone out with a carrier.
 *
 * Protects OUT-25.
 */
final class ShipmentAlreadyDispatched extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self('outbound.shipment_already_dispatched', ['id' => $id], \sprintf('Shipment %s has already been dispatched.', $id));
    }
}
