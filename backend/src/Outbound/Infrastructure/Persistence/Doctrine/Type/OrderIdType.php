<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine\Type;

use App\Outbound\Domain\OrderId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class OrderIdType extends IdentifierType
{
    public const string NAME = 'order_id';

    #[\Override]
    protected function fromDatabase(string $value): OrderId
    {
        return OrderId::fromString($value);
    }
}
