<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine\Type;

use App\Outbound\Domain\WaveId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class WaveIdType extends IdentifierType
{
    public const string NAME = 'wave_id';

    #[\Override]
    protected function fromDatabase(string $value): WaveId
    {
        return WaveId::fromString($value);
    }
}
