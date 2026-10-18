<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A minimum shelf life is negative, or was set on a product that is not tracked by expiry date.
 *
 * Protects CAT-07.
 */
final class InvalidShelfLife extends DomainRuleViolation
{
    public static function forField(string $field, int $days, bool $trackedByExpiry): self
    {
        return new self(
            'catalog.shelf_life_invalid',
            ['field' => $field, 'value' => $days, 'trackedByExpiry' => $trackedByExpiry],
            \sprintf('%s must be zero or more days and only applies to products tracked by expiry date, got %d.', $field, $days),
        );
    }
}
