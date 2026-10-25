<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine\Type;

use App\Outbound\Domain\ShipmentId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class ShipmentIdType extends IdentifierType
{
    public const string NAME = 'shipment_id';

    #[\Override]
    protected function fromDatabase(string $value): ShipmentId
    {
        return ShipmentId::fromString($value);
    }
}
