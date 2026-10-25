<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Persistence\Doctrine\Type;

use App\Audit\Domain\AuditEntryId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class AuditEntryIdType extends IdentifierType
{
    public const string NAME = 'audit_entry_id';

    #[\Override]
    protected function fromDatabase(string $value): AuditEntryId
    {
        return AuditEntryId::fromString($value);
    }
}
