<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The shipment has no parcel with this SSCC.
 */
final class ParcelNotFound extends DomainRuleViolation
{
    public static function forSscc(string $sscc): self
    {
        return new self('outbound.parcel_not_found', ['sscc' => $sscc], \sprintf('No parcel with SSCC %s.', $sscc));
    }
}
