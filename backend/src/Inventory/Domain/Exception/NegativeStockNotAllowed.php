<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A change would leave fewer than zero units in a stock item.
 *
 * Protects STK-01.
 */
final class NegativeStockNotAllowed extends DomainRuleViolation
{
    public static function taking(int $requested, int $onHand): self
    {
        return new self(
            'inventory.stock_negative',
            ['requested' => $requested, 'onHand' => $onHand],
            \sprintf('Cannot take %d units from a stock item that holds %d.', $requested, $onHand),
        );
    }
}
