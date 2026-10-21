<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use App\Inbound\Domain\SupplierName;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class SupplierNameType extends StringValueObjectType
{
    public const string NAME = 'supplier_name';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return SupplierName::fromString($value);
    }
}
