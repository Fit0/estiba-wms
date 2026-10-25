<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A parcel was closed with nothing in it.
 *
 * Protects OUT-21.
 */
final class ParcelEmpty extends DomainRuleViolation
{
    public static function closing(): self
    {
        return new self('outbound.parcel_empty', [], 'A parcel needs at least one product.');
    }
}
