<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * No stock item with this identifier exists for the tenant.
 */
final class StockItemNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self(
            'inventory.stock_item_not_found',
            ['id' => $id],
            \sprintf('No stock item with id %s.', $id),
        );
    }
}
