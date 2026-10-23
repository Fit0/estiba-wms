<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ReservationId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class ReservationIdType extends IdentifierType
{
    public const string NAME = 'reservation_id';

    #[\Override]
    protected function fromDatabase(string $value): ReservationId
    {
        return ReservationId::fromString($value);
    }
}
