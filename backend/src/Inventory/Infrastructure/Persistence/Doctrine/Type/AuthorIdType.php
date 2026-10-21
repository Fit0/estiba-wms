<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\AuthorId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class AuthorIdType extends IdentifierType
{
    public const string NAME = 'stock_author_id';

    #[\Override]
    protected function fromDatabase(string $value): AuthorId
    {
        return AuthorId::fromString($value);
    }
}
