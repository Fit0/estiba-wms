<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The name of a customer is empty, longer than 100 characters or has control characters.
 */
final class InvalidCustomerName extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'outbound.customer_name_invalid',
            [],
            \sprintf('A customer name must have 1 to 100 characters and no control characters, and "%s" was given.', $value),
        );
    }
}
