<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\Email;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class EmailType extends StringValueObjectType
{
    public const string NAME = 'email';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return Email::fromString($value);
    }
}
