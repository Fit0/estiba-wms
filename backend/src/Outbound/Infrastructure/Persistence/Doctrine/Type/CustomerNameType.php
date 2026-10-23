<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine\Type;

use App\Outbound\Domain\CustomerName;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class CustomerNameType extends StringValueObjectType
{
    public const string NAME = 'customer_name';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return CustomerName::fromString($value);
    }
}
