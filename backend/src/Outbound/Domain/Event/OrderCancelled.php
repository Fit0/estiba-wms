<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Event;

use App\Outbound\Domain\OrderId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

final readonly class OrderCancelled implements DomainEvent
{
    public function __construct(
        public OrderId $orderId,
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
