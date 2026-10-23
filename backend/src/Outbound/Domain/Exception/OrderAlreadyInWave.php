<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OrderReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The order already belongs to a wave, so it cannot join another or change.
 *
 * Protects OUT-05 and OUT-07.
 */
final class OrderAlreadyInWave extends DomainRuleViolation
{
    public static function forReference(OrderReference $reference): self
    {
        return new self(
            'outbound.order_already_in_wave',
            ['reference' => $reference->value],
            \sprintf('Order %s already belongs to a wave.', $reference->value),
        );
    }
}
