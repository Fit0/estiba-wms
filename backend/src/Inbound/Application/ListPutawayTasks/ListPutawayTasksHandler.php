<?php

declare(strict_types=1);

namespace App\Inbound\Application\ListPutawayTasks;

use App\Inbound\Domain\PutawayTask;
use App\Inbound\Domain\PutawayTaskRepository;
use App\Inbound\Domain\ReceiptId;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;

final readonly class ListPutawayTasksHandler implements QueryHandler
{
    public function __construct(
        private PutawayTaskRepository $tasks,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return Page<PutawayTask>
     */
    public function __invoke(ListPutawayTasks $query): Page
    {
        return $this->tasks->search(
            $this->tenant->id(),
            null === $query->receiptId ? null : ReceiptId::fromString($query->receiptId),
            $query->status,
            $query->page,
        );
    }
}
