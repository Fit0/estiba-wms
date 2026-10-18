<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;
use App\Warehouse\Domain\ZoneId;

final class ZoneIdType extends IdentifierType
{
    public const string NAME = 'zone_id';

    #[\Override]
    protected function fromDatabase(string $value): ZoneId
    {
        return ZoneId::fromString($value);
    }
}
