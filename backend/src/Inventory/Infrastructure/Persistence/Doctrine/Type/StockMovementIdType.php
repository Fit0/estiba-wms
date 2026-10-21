<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\StockMovementId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class StockMovementIdType extends IdentifierType
{
    public const string NAME = 'stock_movement_id';

    #[\Override]
    protected function fromDatabase(string $value): StockMovementId
    {
        return StockMovementId::fromString($value);
    }
}
