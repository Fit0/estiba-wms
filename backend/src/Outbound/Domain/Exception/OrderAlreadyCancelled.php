<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OrderReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A cancelled order was cancelled or allocated again. A cancelled order is finished.
 *
 * Protects OUT-04.
 */
final class OrderAlreadyCancelled extends DomainRuleViolation
{
    public static function forReference(OrderReference $reference): self
    {
        return new self(
            'outbound.order_already_cancelled',
            ['reference' => $reference->value],
            \sprintf('Order %s is already cancelled.', $reference->value),
        );
    }
}
