<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;
use App\Warehouse\Domain\ZoneId;

/**
 * A location was placed in a zone that does not belong to its warehouse.
 *
 * Protects WH-05.
 */
final class UnknownZone extends DomainRuleViolation
{
    public static function forId(ZoneId $id): self
    {
        return new self(
            'warehouse.zone_unknown',
            ['zoneId' => $id->value],
            \sprintf('The warehouse has no zone %s.', $id->value),
        );
    }
}
