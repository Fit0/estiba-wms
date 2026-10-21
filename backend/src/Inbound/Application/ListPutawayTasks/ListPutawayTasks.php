<?php

declare(strict_types=1);

namespace App\Inbound\Application\ListPutawayTasks;

use App\Inbound\Domain\PutawayTask;
use App\Inbound\Domain\PutawayTaskStatus;
use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;

/**
 * The work list of the operators: the tasks of the tenant, oldest first, optionally of one receipt
 * and in one status.
 *
 * @implements Query<Page<PutawayTask>>
 */
final readonly class ListPutawayTasks implements Query
{
    public function __construct(
        public PageRequest $page,
        public ?string $receiptId = null,
        public ?PutawayTaskStatus $status = null,
    ) {
    }
}
