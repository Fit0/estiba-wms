<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;
use App\Warehouse\Domain\LocationId;

final class LocationIdType extends IdentifierType
{
    public const string NAME = 'location_id';

    #[\Override]
    protected function fromDatabase(string $value): LocationId
    {
        return LocationId::fromString($value);
    }
}
