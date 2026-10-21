<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use App\Inbound\Domain\PutawayTaskId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class PutawayTaskIdType extends IdentifierType
{
    public const string NAME = 'putaway_task_id';

    #[\Override]
    protected function fromDatabase(string $value): PutawayTaskId
    {
        return PutawayTaskId::fromString($value);
    }
}
