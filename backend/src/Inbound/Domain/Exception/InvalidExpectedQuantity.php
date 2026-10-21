<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\ProductId;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A line of an ASN announces zero or fewer units.
 *
 * Protects INB-02.
 */
final class InvalidExpectedQuantity extends DomainRuleViolation
{
    public static function forLine(ProductId $productId, int $units): self
    {
        return new self(
            'inbound.expected_quantity_invalid',
            ['productId' => $productId->value, 'units' => $units],
            \sprintf('An ASN line must expect at least one unit, and %d were given for product %s.', $units, $productId->value),
        );
    }
}
