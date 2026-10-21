<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\LocationId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class StockLocationIdType extends IdentifierType
{
    public const string NAME = 'stock_location_id';

    #[\Override]
    protected function fromDatabase(string $value): LocationId
    {
        return LocationId::fromString($value);
    }
}
