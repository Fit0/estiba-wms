<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Event;

use App\Outbound\Domain\PickListId;
use App\Outbound\Domain\WaveId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

/**
 * Every line of the list is picked or shorted.
 */
final readonly class PickListCompleted implements DomainEvent
{
    public function __construct(
        public PickListId $pickListId,
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
