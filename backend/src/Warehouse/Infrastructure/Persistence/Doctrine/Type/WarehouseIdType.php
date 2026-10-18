<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;
use App\Warehouse\Domain\WarehouseId;

final class WarehouseIdType extends IdentifierType
{
    public const string NAME = 'warehouse_id';

    #[\Override]
    protected function fromDatabase(string $value): WarehouseId
    {
        return WarehouseId::fromString($value);
    }
}
