<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\StockItemId;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A transfer names the same stock item as source and destination.
 *
 * Protects STK-08.
 */
final class TransferToSameStockItem extends DomainRuleViolation
{
    public static function of(StockItemId $id): self
    {
        return new self(
            'inventory.transfer_same_stock_item',
            ['stockItemId' => $id->value],
            \sprintf('Stock item %s cannot be transferred to itself.', $id->value),
        );
    }
}
