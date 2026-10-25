<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A parcel was closed, or packing completed, on a shipment whose packing is done.
 *
 * Protects OUT-24.
 */
final class ShipmentAlreadyPacked extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self('outbound.shipment_already_packed', ['id' => $id], \sprintf('Shipment %s is already packed.', $id));
    }
}
