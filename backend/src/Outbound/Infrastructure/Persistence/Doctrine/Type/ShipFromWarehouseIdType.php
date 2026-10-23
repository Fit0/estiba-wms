<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine\Type;

use App\Outbound\Domain\WarehouseId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class ShipFromWarehouseIdType extends IdentifierType
{
    public const string NAME = 'outbound_warehouse_id';

    #[\Override]
    protected function fromDatabase(string $value): WarehouseId
    {
        return WarehouseId::fromString($value);
    }
}
