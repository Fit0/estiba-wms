<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;
use App\Warehouse\Domain\LocationCode;

/**
 * The warehouse already has a location with this code.
 *
 * Protects WH-03.
 */
final class LocationCodeAlreadyUsed extends DomainRuleViolation
{
    public static function forCode(LocationCode $code): self
    {
        return new self(
            'warehouse.location_code_already_used',
            ['code' => $code->value],
            \sprintf('The warehouse already has a location with code %s.', $code->value),
        );
    }
}
