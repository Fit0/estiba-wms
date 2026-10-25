<?php

declare(strict_types=1);

namespace App\Inbound\Application\SplitPutawayTask;

use App\Inbound\Application\PutawayTaskLookup;
use App\Inbound\Domain\PutawayTask;
use App\Inbound\Domain\PutawayTaskId;
use App\Inbound\Domain\PutawayTaskRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\IdGenerator;

final readonly class SplitPutawayTaskHandler implements CommandHandler
{
    public function __construct(
        private PutawayTaskLookup $lookup,
        private PutawayTaskRepository $tasks,
        private IdGenerator $ids,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(SplitPutawayTask $command): PutawayTask
    {
        $task = $this->lookup->get($command->taskId);

        $newTask = $task->split(PutawayTaskId::fromString($this->ids->next()), $command->units, $this->clock->now());
        $this->tasks->save($newTask);

        $this->events->publish(...$task->pullEvents(), ...$newTask->pullEvents());

        return $newTask;
    }
}
