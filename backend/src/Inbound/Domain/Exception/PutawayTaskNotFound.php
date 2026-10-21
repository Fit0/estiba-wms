<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * No putaway task with this identifier exists for the tenant.
 */
final class PutawayTaskNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self(
            'inbound.putaway_task_not_found',
            ['id' => $id],
            \sprintf('No putaway task with id %s.', $id),
        );
    }
}
