<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * An order names a warehouse the tenant does not have.
 */
final class UnknownShipFromWarehouse extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self(
            'outbound.warehouse_unknown',
            ['id' => $id],
            \sprintf('Warehouse %s does not exist, so an order cannot be served from it.', $id),
        );
    }
}
