<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use App\Inbound\Domain\AsnId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class AsnIdType extends IdentifierType
{
    public const string NAME = 'asn_id';

    #[\Override]
    protected function fromDatabase(string $value): AsnId
    {
        return AsnId::fromString($value);
    }
}
