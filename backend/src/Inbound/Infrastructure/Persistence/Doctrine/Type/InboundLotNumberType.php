<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use App\Inbound\Domain\LotNumber;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class InboundLotNumberType extends StringValueObjectType
{
    public const string NAME = 'inbound_lot_number';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return LotNumber::fromString($value);
    }
}
