<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The orders of a wave are served from different warehouses, but a pick list walks one warehouse.
 *
 * Protects OUT-14.
 */
final class WaveWarehouseMismatch extends DomainRuleViolation
{
    public static function between(): self
    {
        return new self('outbound.wave_warehouse_mismatch', [], 'All the orders of a wave must be served from the same warehouse.');
    }
}
