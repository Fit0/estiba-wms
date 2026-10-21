<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Event;

use App\Inbound\Domain\PutawayTaskId;
use App\Shared\Domain\DomainEvent;

/**
 * A task gave part of its units to a new task, so they can go to another location (INB-12).
 */
final readonly class PutawayTaskSplit implements DomainEvent
{
    public function __construct(
        public PutawayTaskId $taskId,
        public PutawayTaskId $newTaskId,
        public int $units,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
