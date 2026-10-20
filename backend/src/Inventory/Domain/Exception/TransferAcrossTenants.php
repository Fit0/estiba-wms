<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A transfer reaches a stock item of another tenant.
 *
 * Protects STK-08 and the isolation of tenants.
 */
final class TransferAcrossTenants extends DomainRuleViolation
{
    public static function attempted(): self
    {
        return new self(
            'inventory.transfer_across_tenants',
            [],
            'Units can only be transferred between stock items of the same tenant.',
        );
    }
}
