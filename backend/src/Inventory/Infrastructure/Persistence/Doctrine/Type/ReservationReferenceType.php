<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ReservationReference;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class ReservationReferenceType extends StringValueObjectType
{
    public const string NAME = 'reservation_reference';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return ReservationReference::fromString($value);
    }
}
