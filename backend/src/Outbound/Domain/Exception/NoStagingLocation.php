<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The warehouse has no active staging location to take the picked units to.
 */
final class NoStagingLocation extends DomainRuleViolation
{
    public static function in(string $warehouseId): self
    {
        return new self(
            'outbound.no_staging_location',
            ['warehouseId' => $warehouseId],
            \sprintf('Warehouse %s has no active staging location for the picked units.', $warehouseId),
        );
    }
}
