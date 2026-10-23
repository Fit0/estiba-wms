<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OrderStatus;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The backorder of an order was accepted, but the order is not partly covered, so it has none.
 */
final class BackorderNotApplicable extends DomainRuleViolation
{
    public static function forStatus(OrderStatus $status): self
    {
        return new self(
            'outbound.backorder_not_applicable',
            ['status' => $status->value],
            \sprintf('Only a partially allocated order has a backorder to accept, and this one is %s.', $status->value),
        );
    }
}
