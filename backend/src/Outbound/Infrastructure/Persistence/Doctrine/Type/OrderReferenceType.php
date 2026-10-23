<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine\Type;

use App\Outbound\Domain\OrderReference;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class OrderReferenceType extends StringValueObjectType
{
    public const string NAME = 'order_reference';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return OrderReference::fromString($value);
    }
}
