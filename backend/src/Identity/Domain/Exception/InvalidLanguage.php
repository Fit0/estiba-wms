<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A language is not one the product speaks.
 */
final class InvalidLanguage extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'identity.language_invalid',
            ['value' => $value],
            \sprintf('"%s" is not a language. Use es or en.', $value),
        );
    }
}
