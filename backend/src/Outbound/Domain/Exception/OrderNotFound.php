<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * No order with this identifier exists for the tenant.
 */
final class OrderNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self(
            'outbound.order_not_found',
            ['id' => $id],
            \sprintf('No order with id %s.', $id),
        );
    }
}
