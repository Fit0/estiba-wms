<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OrderReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * An order of a wave that is already on the floor was cancelled. Its units are on pick lists.
 *
 * Protects OUT-07.
 */
final class OrderAlreadyInReleasedWave extends DomainRuleViolation
{
    public static function forReference(OrderReference $reference): self
    {
        return new self(
            'outbound.order_already_in_released_wave',
            ['reference' => $reference->value],
            \sprintf('Order %s belongs to a wave that was released, so it cannot be cancelled. Cancel the wave first.', $reference->value),
        );
    }
}
