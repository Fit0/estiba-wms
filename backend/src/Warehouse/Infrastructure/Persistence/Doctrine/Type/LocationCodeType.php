<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;
use App\Warehouse\Domain\LocationCode;

final class LocationCodeType extends StringValueObjectType
{
    public const string NAME = 'location_code';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return LocationCode::fromString($value);
    }
}
