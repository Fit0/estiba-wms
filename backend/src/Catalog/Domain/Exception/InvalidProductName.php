<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A product name is empty, longer than 150 characters or has control characters.
 */
final class InvalidProductName extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'catalog.product_name_invalid',
            ['value' => $value],
            'A product name must have 1 to 150 characters and no control characters.',
        );
    }
}
