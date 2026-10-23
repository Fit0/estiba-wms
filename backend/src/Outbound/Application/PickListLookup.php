<?php

declare(strict_types=1);

namespace App\Outbound\Application;

use App\Outbound\Domain\Exception\PickListNotFound;
use App\Outbound\Domain\PickList;
use App\Outbound\Domain\PickListId;
use App\Outbound\Domain\PickListRepository;
use App\Shared\Application\CurrentTenant;

/**
 * Finds a pick list of the current tenant. A list of another tenant is reported as not found.
 */
final readonly class PickListLookup
{
    public function __construct(
        private PickListRepository $pickLists,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws PickListNotFound
     */
    public function get(string $id): PickList
    {
        $pickList = $this->pickLists->find(PickListId::fromString($id));

        if (!$pickList instanceof PickList || !$pickList->tenantId()->equals($this->tenant->id())) {
            throw PickListNotFound::forId($id);
        }

        return $pickList;
    }
}
