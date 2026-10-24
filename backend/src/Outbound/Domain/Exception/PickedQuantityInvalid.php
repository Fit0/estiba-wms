<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A negative number of units was reported as picked.
 */
final class PickedQuantityInvalid extends DomainRuleViolation
{
    public static function forUnits(int $units): self
    {
        return new self('outbound.picked_quantity_invalid', ['units' => $units], \sprintf('%d units cannot be picked.', $units));
    }
}
