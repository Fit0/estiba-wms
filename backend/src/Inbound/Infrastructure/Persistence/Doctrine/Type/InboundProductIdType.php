<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use App\Inbound\Domain\ProductId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class InboundProductIdType extends IdentifierType
{
    public const string NAME = 'inbound_product_id';

    #[\Override]
    protected function fromDatabase(string $value): ProductId
    {
        return ProductId::fromString($value);
    }
}
