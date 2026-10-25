<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OrderReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The goods of the order have left the warehouse, so it can no longer be cancelled or shipped again.
 *
 * Protects OUT-04 and OUT-25.
 */
final class OrderAlreadyShipped extends DomainRuleViolation
{
    public static function forReference(OrderReference $reference): self
    {
        return new self('outbound.order_already_shipped', ['reference' => $reference->value], \sprintf('Order %s has already been shipped.', $reference->value));
    }
}
