<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Event;

use App\Outbound\Domain\PickListId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

final readonly class PickLineConfirmed implements DomainEvent
{
    public function __construct(
        public PickListId $pickListId,
        public int $position,
        public int $quantityPicked,
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
