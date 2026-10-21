<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\LocationId;
use App\Shared\Domain\DomainRuleViolation;

/**
 * Goods went somewhere other than the suggested location and no reason was given.
 *
 * Protects INB-13.
 */
final class OverrideReasonRequired extends DomainRuleViolation
{
    public static function insteadOf(LocationId $suggested): self
    {
        return new self(
            'inbound.putaway_override_reason_required',
            ['suggestedLocationId' => $suggested->value],
            \sprintf('The suggested location was %s. Putting the goods elsewhere needs a reason.', $suggested->value),
        );
    }
}
