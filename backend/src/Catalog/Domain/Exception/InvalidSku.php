<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A SKU is empty, too long or has characters that are not allowed.
 *
 * Protects CAT-01.
 */
final class InvalidSku extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'catalog.sku_invalid',
            ['value' => $value],
            \sprintf('"%s" is not a valid SKU. Use 1 to 40 letters, digits, dots, dashes, underscores or slashes, starting and ending with a letter or digit.', $value),
        );
    }
}
