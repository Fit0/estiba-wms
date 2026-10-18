<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A dimension or the weight is not a positive whole number.
 *
 * Protects CAT-09.
 */
final class InvalidDimensions extends DomainRuleViolation
{
    public static function forField(string $field, int $value): self
    {
        return new self(
            'catalog.dimensions_invalid',
            ['field' => $field, 'value' => $value],
            \sprintf('%s must be a positive whole number, got %d.', $field, $value),
        );
    }
}
