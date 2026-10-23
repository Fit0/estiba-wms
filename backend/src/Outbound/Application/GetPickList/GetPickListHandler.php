<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetPickList;

use App\Outbound\Application\PickListLookup;
use App\Outbound\Domain\Exception\PickListNotFound;
use App\Outbound\Domain\PickList;
use App\Shared\Application\Bus\QueryHandler;

final readonly class GetPickListHandler implements QueryHandler
{
    public function __construct(private PickListLookup $lookup)
    {
    }

    /**
     * @throws PickListNotFound
     */
    public function __invoke(GetPickList $query): PickList
    {
        return $this->lookup->get($query->pickListId);
    }
}
