<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\Sku;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class SkuType extends StringValueObjectType
{
    public const string NAME = 'sku';

    #[\Override]
    protected function fromDatabase(string $value): Sku
    {
        return Sku::fromString($value);
    }
}
