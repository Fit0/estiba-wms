<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The name of a carrier is empty, longer than 60 characters or has control characters.
 */
final class InvalidCarrierName extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'outbound.carrier_name_invalid',
            [],
            \sprintf('A carrier must have 1 to 60 characters and no control characters, and "%s" was given.', $value),
        );
    }
}
