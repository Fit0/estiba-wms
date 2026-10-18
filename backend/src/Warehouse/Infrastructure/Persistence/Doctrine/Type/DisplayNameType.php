<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;
use App\Warehouse\Domain\DisplayName;

final class DisplayNameType extends StringValueObjectType
{
    public const string NAME = 'display_name';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return DisplayName::fromString($value);
    }
}
