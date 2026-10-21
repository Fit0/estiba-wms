<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\MovementReference;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class MovementReferenceType extends StringValueObjectType
{
    public const string NAME = 'movement_reference';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return MovementReference::fromString($value);
    }
}
