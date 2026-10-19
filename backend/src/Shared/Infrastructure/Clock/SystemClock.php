<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Clock;

use App\Shared\Application\Clock;

final readonly class SystemClock implements Clock
{
    #[\Override]
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
