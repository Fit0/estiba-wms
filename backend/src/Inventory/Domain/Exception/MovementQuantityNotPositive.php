<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\MovementType;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A movement that changes nothing was asked for. A ledger records changes, so a movement of zero
 * units has no place in it.
 *
 * Protects STK-03.
 */
final class MovementQuantityNotPositive extends DomainRuleViolation
{
    public static function for(MovementType $type): self
    {
        return new self(
            'inventory.movement_quantity_not_positive',
            ['type' => $type->value],
            \sprintf('A %s movement must change the stock by at least one unit.', $type->value),
        );
    }
}
