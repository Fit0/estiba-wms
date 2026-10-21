<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ProductFamily;

final class ProductFamilyType extends StringValueObjectType
{
    public const string NAME = 'product_family';

    #[\Override]
    protected function fromDatabase(string $value): ProductFamily
    {
        return ProductFamily::fromString($value);
    }
}
