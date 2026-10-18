<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * No location with this identifier exists for the tenant.
 */
final class LocationNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self(
            'warehouse.location_not_found',
            ['id' => $id],
            \sprintf('No location with id %s.', $id),
        );
    }
}
