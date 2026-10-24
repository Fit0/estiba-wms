<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OrderReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A picked order was cancelled. Its goods are no longer on the shelves.
 */
final class OrderAlreadyPicked extends DomainRuleViolation
{
    public static function forReference(OrderReference $reference): self
    {
        return new self(
            'outbound.order_already_picked',
            ['reference' => $reference->value],
            \sprintf('Order %s is already picked, so it cannot be cancelled.', $reference->value),
        );
    }
}
