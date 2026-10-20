<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A domain error whose code has no translation yet, to check the English fallback.
 */
final class UntranslatedError extends DomainRuleViolation
{
    public static function make(): self
    {
        return new self('test.not_translated_yet', [], 'English text for developers.');
    }
}
