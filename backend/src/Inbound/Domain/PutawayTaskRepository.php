<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;

/**
 * There is no delete method: a putaway task is a record of where goods went.
 */
interface PutawayTaskRepository
{
    public function find(PutawayTaskId $id): ?PutawayTask;

    /**
     * The tenant's tasks, oldest first, optionally narrowed to a receipt and to a status.
     *
     * @return Page<PutawayTask>
     */
    public function search(TenantId $tenantId, ?ReceiptId $receiptId, ?PutawayTaskStatus $status, PageRequest $page): Page;

    public function save(PutawayTask $task): void;
}
