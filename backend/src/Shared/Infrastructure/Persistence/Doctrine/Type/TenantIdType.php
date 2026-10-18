<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\TenantId;

final class TenantIdType extends IdentifierType
{
    public const string NAME = 'tenant_id';

    #[\Override]
    protected function fromDatabase(string $value): TenantId
    {
        return TenantId::fromString($value);
    }
}
