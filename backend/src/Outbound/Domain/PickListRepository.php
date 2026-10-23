<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Shared\Domain\TenantId;

/**
 * There is no delete method: a list that will not be walked is cancelled with its wave.
 */
interface PickListRepository
{
    public function find(PickListId $id): ?PickList;

    /**
     * The tenant's pick lists, the latest first, optionally those of one wave.
     *
     * @return list<PickList>
     */
    public function findOf(TenantId $tenantId, ?WaveId $waveId): array;

    public function save(PickList $pickList): void;
}
