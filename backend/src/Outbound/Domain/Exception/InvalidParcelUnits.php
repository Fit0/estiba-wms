<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A parcel holds a number of units of a product that is not a positive whole number.
 *
 * Protects OUT-21.
 */
final class InvalidParcelUnits extends DomainRuleViolation
{
    public static function of(string $productId, int $units): self
    {
        return new self('outbound.parcel_units_invalid', ['productId' => $productId, 'units' => $units], \sprintf('%d is not a number of units a parcel can hold of product %s.', $units, $productId));
    }
}
