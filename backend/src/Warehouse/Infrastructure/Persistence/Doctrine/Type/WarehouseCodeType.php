<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;
use App\Warehouse\Domain\WarehouseCode;

final class WarehouseCodeType extends StringValueObjectType
{
    public const string NAME = 'warehouse_code';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return WarehouseCode::fromString($value);
    }
}
