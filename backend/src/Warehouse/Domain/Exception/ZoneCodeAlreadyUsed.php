<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;
use App\Warehouse\Domain\ZoneCode;

/**
 * The warehouse already has a zone with this code.
 *
 * Protects WH-02.
 */
final class ZoneCodeAlreadyUsed extends DomainRuleViolation
{
    public static function forCode(ZoneCode $code): self
    {
        return new self(
            'warehouse.zone_code_already_used',
            ['code' => $code->value],
            \sprintf('The warehouse already has a zone with code %s.', $code->value),
        );
    }
}
