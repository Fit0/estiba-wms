<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Event;

use App\Outbound\Domain\OperatorId;
use App\Outbound\Domain\PickListId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

final readonly class PickListStarted implements DomainEvent
{
    public function __construct(
        public PickListId $pickListId,
        public OperatorId $operatorId,
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
