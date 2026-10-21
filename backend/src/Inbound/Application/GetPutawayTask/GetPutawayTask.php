<?php

declare(strict_types=1);

namespace App\Inbound\Application\GetPutawayTask;

use App\Inbound\Domain\PutawayTask;
use App\Shared\Application\Bus\Query;

/**
 * @implements Query<PutawayTask>
 */
final readonly class GetPutawayTask implements Query
{
    public function __construct(public string $taskId)
    {
    }
}
