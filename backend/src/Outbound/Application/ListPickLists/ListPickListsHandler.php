<?php

declare(strict_types=1);

namespace App\Outbound\Application\ListPickLists;

use App\Outbound\Domain\PickList;
use App\Outbound\Domain\PickListRepository;
use App\Outbound\Domain\WaveId;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;

final readonly class ListPickListsHandler implements QueryHandler
{
    public function __construct(
        private PickListRepository $pickLists,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return list<PickList>
     */
    public function __invoke(ListPickLists $query): array
    {
        return $this->pickLists->findOf($this->tenant->id(), null === $query->waveId ? null : WaveId::fromString($query->waveId));
    }
}
