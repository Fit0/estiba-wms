<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;
use App\Warehouse\Domain\LocationCode;

/**
 * A location that is not blocked was unblocked.
 *
 * Protects WH-06.
 */
final class LocationNotBlocked extends DomainRuleViolation
{
    public static function forCode(LocationCode $code): self
    {
        return new self(
            'warehouse.location_not_blocked',
            ['code' => $code->value],
            \sprintf('Location %s is not blocked.', $code->value),
        );
    }
}
