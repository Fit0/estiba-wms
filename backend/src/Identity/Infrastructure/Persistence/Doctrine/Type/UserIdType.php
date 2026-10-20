<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\UserId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class UserIdType extends IdentifierType
{
    public const string NAME = 'user_id';

    #[\Override]
    protected function fromDatabase(string $value): UserId
    {
        return UserId::fromString($value);
    }
}
