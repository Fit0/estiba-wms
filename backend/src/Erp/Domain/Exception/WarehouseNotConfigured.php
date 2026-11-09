<?php

declare(strict_types=1);

namespace App\Erp\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The warehouse that serves the orders of the ERP is not one of the tenant: the setting names a code
 * the tenant does not have.
 */
final class WarehouseNotConfigured extends DomainRuleViolation
{
    public static function forCode(string $code): self
    {
        return new self(
            'erp.warehouse_unknown',
            ['code' => $code],
            \sprintf('The warehouse %s that serves the ERP orders does not exist.', $code),
        );
    }
}
