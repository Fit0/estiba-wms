<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A GTIN has the wrong length, has characters that are not digits or fails the GS1 check digit.
 *
 * Protects CAT-02.
 */
final class InvalidGtin extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'catalog.gtin_invalid',
            ['value' => $value],
            \sprintf('"%s" is not a valid GTIN. Expected 8, 12, 13 or 14 digits with a correct GS1 check digit.', $value),
        );
    }
}
