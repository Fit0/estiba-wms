<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * No warehouse with this identifier exists for the tenant.
 */
final class WarehouseNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self(
            'warehouse.warehouse_not_found',
            ['id' => $id],
            \sprintf('No warehouse with id %s.', $id),
        );
    }
}
