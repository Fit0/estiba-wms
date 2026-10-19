<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\ProductName;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class ProductNameType extends StringValueObjectType
{
    public const string NAME = 'product_name';

    #[\Override]
    protected function fromDatabase(string $value): ProductName
    {
        return ProductName::fromString($value);
    }
}
