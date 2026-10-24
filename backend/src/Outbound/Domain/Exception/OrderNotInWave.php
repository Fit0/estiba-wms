<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OrderReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A pick was recorded on an order that is not in a wave on the floor.
 */
final class OrderNotInWave extends DomainRuleViolation
{
    public static function forReference(OrderReference $reference): self
    {
        return new self('outbound.order_not_in_wave', ['reference' => $reference->value], \sprintf('Order %s is not in a wave, so nothing can be picked for it.', $reference->value));
    }
}
