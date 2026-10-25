<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OrderReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * An order is packed in one shipment, and it already has it.
 *
 * Protects OUT-20.
 */
final class PackingAlreadyStarted extends DomainRuleViolation
{
    public static function forOrder(OrderReference $reference): self
    {
        return new self('outbound.packing_already_started', ['reference' => $reference->value], \sprintf('Packing of order %s has already started.', $reference->value));
    }
}
