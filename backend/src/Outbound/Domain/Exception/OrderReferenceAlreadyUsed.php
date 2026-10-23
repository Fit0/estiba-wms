<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OrderReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The tenant already has an order with this reference, so registering it again would send the same order twice.
 *
 * Protects OUT-01.
 */
final class OrderReferenceAlreadyUsed extends DomainRuleViolation
{
    public static function forReference(OrderReference $reference): self
    {
        return new self(
            'outbound.order_reference_already_used',
            ['reference' => $reference->value],
            \sprintf('The tenant already has an order with reference %s.', $reference->value),
        );
    }
}
