<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Shared\Domain\DomainEvent;

/**
 * An event no policy handles.
 */
final readonly class NobodyListens implements DomainEvent
{
    public function occurredAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-21T10:00:00+00:00');
    }
}
