<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;
use App\Warehouse\Domain\BlockReason;

final class BlockReasonType extends StringValueObjectType
{
    public const string NAME = 'block_reason';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return BlockReason::fromString($value);
    }
}
