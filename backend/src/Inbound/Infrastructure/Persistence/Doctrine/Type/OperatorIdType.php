<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use App\Inbound\Domain\OperatorId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class OperatorIdType extends IdentifierType
{
    public const string NAME = 'operator_id';

    #[\Override]
    protected function fromDatabase(string $value): OperatorId
    {
        return OperatorId::fromString($value);
    }
}
