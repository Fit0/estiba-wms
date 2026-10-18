<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;
use App\Warehouse\Domain\WarehouseCode;

/**
 * The tenant already has a warehouse with this code.
 *
 * Protects WH-01.
 */
final class WarehouseCodeAlreadyUsed extends DomainRuleViolation
{
    public static function forCode(WarehouseCode $code): self
    {
        return new self(
            'warehouse.warehouse_code_already_used',
            ['code' => $code->value],
            \sprintf('The tenant already has a warehouse with code %s.', $code->value),
        );
    }
}
