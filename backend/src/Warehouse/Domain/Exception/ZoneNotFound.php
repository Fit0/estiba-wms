<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The warehouse has no zone with this identifier.
 */
final class ZoneNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self(
            'warehouse.zone_not_found',
            ['id' => $id],
            \sprintf('No zone with id %s in this warehouse.', $id),
        );
    }
}
