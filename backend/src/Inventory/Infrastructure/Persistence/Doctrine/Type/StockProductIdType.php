<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ProductId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class StockProductIdType extends IdentifierType
{
    public const string NAME = 'stock_product_id';

    #[\Override]
    protected function fromDatabase(string $value): ProductId
    {
        return ProductId::fromString($value);
    }
}
