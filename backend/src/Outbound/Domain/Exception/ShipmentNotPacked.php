<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A shipment was dispatched before its packing was complete.
 *
 * Protects OUT-25.
 */
final class ShipmentNotPacked extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self('outbound.shipment_not_packed', ['id' => $id], \sprintf('Shipment %s is not packed, so it cannot be dispatched.', $id));
    }
}
