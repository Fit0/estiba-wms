<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\PutawayTaskId;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A putaway task that is already confirmed was asked to change.
 */
final class PutawayTaskAlreadyConfirmed extends DomainRuleViolation
{
    public static function forId(PutawayTaskId $id): self
    {
        return new self(
            'inbound.putaway_task_already_confirmed',
            ['id' => $id->value],
            \sprintf('Putaway task %s is already confirmed.', $id->value),
        );
    }
}
