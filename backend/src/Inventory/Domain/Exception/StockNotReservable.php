<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\StockItemId;
use App\Inventory\Domain\StockStatus;
use App\Shared\Domain\DomainRuleViolation;

/**
 * Units that are in quarantine, damaged or on hold were asked to be reserved.
 *
 * Protects STK-06.
 */
final class StockNotReservable extends DomainRuleViolation
{
    public static function inStatus(StockItemId $id, StockStatus $status): self
    {
        return new self(
            'inventory.stock_not_reservable',
            ['stockItemId' => $id->value, 'status' => $status->value],
            \sprintf('Stock item %s is %s, and only available stock can be reserved.', $id->value, $status->value),
        );
    }
}
