<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OrderReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The reference of an order is empty, too long or has control characters.
 */
final class InvalidOrderReference extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'outbound.order_reference_invalid',
            ['maxLength' => OrderReference::MAX_LENGTH],
            \sprintf('The reference of an order must have 1 to %d characters and no control characters, and "%s" was given.', OrderReference::MAX_LENGTH, $value),
        );
    }
}
