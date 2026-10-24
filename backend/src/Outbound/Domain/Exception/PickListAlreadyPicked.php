<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A pick list with picks was called off. Its goods have left the shelves, so the list is walked to the end.
 */
final class PickListAlreadyPicked extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self('outbound.pick_list_already_picked', ['id' => $id], \sprintf('Pick list %s already has picks confirmed, so it cannot be cancelled.', $id));
    }
}
