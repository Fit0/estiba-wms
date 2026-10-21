<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use App\Inbound\Domain\OverrideReason;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\StringValueObjectType;

final class OverrideReasonType extends StringValueObjectType
{
    public const string NAME = 'override_reason';

    #[\Override]
    protected function fromDatabase(string $value): \Stringable
    {
        return OverrideReason::fromString($value);
    }
}
