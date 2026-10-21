<?php

declare(strict_types=1);

namespace App\Shared\Domain;

final class InvalidProductFamily extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'shared.product_family_invalid',
            ['value' => $value],
            \sprintf('"%s" is not a valid product family. Use 1 to 20 letters or digits, with single hyphens between groups.', $value),
        );
    }
}
