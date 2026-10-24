<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A pick list that an operator already started was assigned again.
 *
 * Protects OUT-12.
 */
final class PickListAlreadyStarted extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self('outbound.pick_list_already_started', ['id' => $id], \sprintf('Pick list %s is already started, so it cannot be assigned again.', $id));
    }
}
