<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * No shipment with this identifier exists for the tenant.
 */
final class ShipmentNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self('outbound.shipment_not_found', ['id' => $id], \sprintf('No shipment with id %s.', $id));
    }
}
