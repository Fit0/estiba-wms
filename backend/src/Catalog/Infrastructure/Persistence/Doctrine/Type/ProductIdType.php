<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\ProductId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class ProductIdType extends IdentifierType
{
    public const string NAME = 'product_id';

    #[\Override]
    protected function fromDatabase(string $value): ProductId
    {
        return ProductId::fromString($value);
    }
}
