<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use App\Inbound\Domain\LocationId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class DockLocationIdType extends IdentifierType
{
    public const string NAME = 'inbound_location_id';

    #[\Override]
    protected function fromDatabase(string $value): LocationId
    {
        return LocationId::fromString($value);
    }
}
