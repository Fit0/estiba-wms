<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\TenantName;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class TenantNameType extends StringValueObjectType
{
    public const string NAME = 'tenant_name';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return TenantName::fromString($value);
    }
}
