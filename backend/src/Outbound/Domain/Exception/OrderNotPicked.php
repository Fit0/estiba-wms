<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OrderReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * Packing was asked for an order that has not been picked.
 */
final class OrderNotPicked extends DomainRuleViolation
{
    public static function forOrder(OrderReference $reference, string $status): self
    {
        return new self('outbound.order_not_picked', ['reference' => $reference->value, 'status' => $status], \sprintf('Order %s is %s, and only a picked order can be packed.', $reference->value, $status));
    }
}
