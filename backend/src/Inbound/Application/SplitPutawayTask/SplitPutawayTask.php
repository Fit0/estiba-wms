<?php

declare(strict_types=1);

namespace App\Inbound\Application\SplitPutawayTask;

use App\Inbound\Domain\PutawayTask;
use App\Shared\Application\Bus\Command;

/**
 * Takes some units out of a task into a new one, so they can go to another location (INB-12).
 *
 * @implements Command<PutawayTask> the new task
 */
final readonly class SplitPutawayTask implements Command
{
    public function __construct(
        public string $taskId,
        public int $units,
    ) {
    }
}
