<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\StockItemId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class StockItemIdType extends IdentifierType
{
    public const string NAME = 'stock_item_id';

    #[\Override]
    protected function fromDatabase(string $value): StockItemId
    {
        return StockItemId::fromString($value);
    }
}
