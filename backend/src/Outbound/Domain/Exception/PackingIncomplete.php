<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * Packing was completed while picked units are still outside a parcel.
 *
 * Protects OUT-23.
 */
final class PackingIncomplete extends DomainRuleViolation
{
    public static function missing(int $units): self
    {
        return new self('outbound.packing_incomplete', ['units' => $units], \sprintf('%d picked units are not in a parcel yet.', $units));
    }
}
