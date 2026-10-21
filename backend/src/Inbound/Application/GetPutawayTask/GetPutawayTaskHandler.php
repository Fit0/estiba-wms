<?php

declare(strict_types=1);

namespace App\Inbound\Application\GetPutawayTask;

use App\Inbound\Application\PutawayTaskLookup;
use App\Inbound\Domain\Exception\PutawayTaskNotFound;
use App\Inbound\Domain\PutawayTask;
use App\Shared\Application\Bus\QueryHandler;

final readonly class GetPutawayTaskHandler implements QueryHandler
{
    public function __construct(private PutawayTaskLookup $lookup)
    {
    }

    /**
     * @throws PutawayTaskNotFound
     */
    public function __invoke(GetPutawayTask $query): PutawayTask
    {
        return $this->lookup->get($query->taskId);
    }
}
