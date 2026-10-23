<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;

/**
 * There is no delete method: a wave that will not go out is cancelled, because pick lists and
 * orders refer to it.
 */
interface WaveRepository
{
    public function find(WaveId $id): ?Wave;

    /**
     * The tenant's waves, the latest first, optionally narrowed to one status.
     *
     * @return Page<Wave>
     */
    public function search(TenantId $tenantId, ?WaveStatus $status, PageRequest $page): Page;

    public function save(Wave $wave): void;
}
