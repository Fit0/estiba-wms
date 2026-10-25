<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A parcel was closed with a weight that is not positive or is absurd.
 *
 * Protects OUT-21.
 */
final class InvalidParcelWeight extends DomainRuleViolation
{
    public static function of(int $grams, int $max): self
    {
        return new self('outbound.parcel_weight_invalid', ['grams' => $grams, 'max' => $max], \sprintf('A parcel weighs between 1 and %d grams, not %d.', $max, $grams));
    }
}
