<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Event;

use App\Outbound\Domain\WaveId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

/**
 * The wave is on the floor and its pick lists exist.
 */
final readonly class WaveReleased implements DomainEvent
{
    public function __construct(
        public WaveId $waveId,
        public TenantId $tenantId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    #[\Override]
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
