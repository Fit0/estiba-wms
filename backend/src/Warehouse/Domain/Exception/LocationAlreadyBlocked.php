<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;
use App\Warehouse\Domain\LocationCode;

/**
 * A location that is already blocked was blocked again.
 *
 * Protects WH-06.
 */
final class LocationAlreadyBlocked extends DomainRuleViolation
{
    public static function forCode(LocationCode $code): self
    {
        return new self(
            'warehouse.location_already_blocked',
            ['code' => $code->value],
            \sprintf('Location %s is already blocked.', $code->value),
        );
    }
}
