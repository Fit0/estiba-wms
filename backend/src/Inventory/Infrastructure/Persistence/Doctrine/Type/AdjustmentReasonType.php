<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\AdjustmentReason;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class AdjustmentReasonType extends StringValueObjectType
{
    public const string NAME = 'adjustment_reason';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return AdjustmentReason::fromString($value);
    }
}
