<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * Not 18 digits, or the last one is not the GS1 check digit of the rest.
 *
 * Protects OUT-19.
 */
final class InvalidSscc extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self('outbound.sscc_invalid', ['value' => $value], \sprintf('"%s" is not a valid SSCC.', $value));
    }
}
