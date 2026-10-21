<?php

declare(strict_types=1);

namespace App\Inbound\Application;

use App\Inbound\Domain\Exception\PutawayTaskNotFound;
use App\Inbound\Domain\PutawayTask;
use App\Inbound\Domain\PutawayTaskId;
use App\Inbound\Domain\PutawayTaskRepository;
use App\Shared\Application\CurrentTenant;

/**
 * Finds a putaway task of the current tenant. A task of another tenant is reported as not found.
 */
final readonly class PutawayTaskLookup
{
    public function __construct(
        private PutawayTaskRepository $tasks,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws PutawayTaskNotFound
     */
    public function get(string $id): PutawayTask
    {
        $task = $this->tasks->find(PutawayTaskId::fromString($id));

        if (!$task instanceof PutawayTask || !$task->tenantId()->equals($this->tenant->id())) {
            throw PutawayTaskNotFound::forId($id);
        }

        return $task;
    }
}
