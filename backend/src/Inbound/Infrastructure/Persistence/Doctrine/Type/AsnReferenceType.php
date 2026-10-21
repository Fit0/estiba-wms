<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use App\Inbound\Domain\AsnReference;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class AsnReferenceType extends StringValueObjectType
{
    public const string NAME = 'asn_reference';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return AsnReference::fromString($value);
    }
}
