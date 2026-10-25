<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OrderReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * Everything allocated to the order was short picked, so there is nothing to put in a parcel.
 *
 * Protects OUT-20.
 */
final class NothingToPack extends DomainRuleViolation
{
    public static function forOrder(OrderReference $reference): self
    {
        return new self('outbound.nothing_to_pack', ['reference' => $reference->value], \sprintf('Nothing was picked for order %s.', $reference->value));
    }
}
