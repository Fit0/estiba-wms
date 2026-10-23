<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Event;

use App\Outbound\Domain\OrderId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

/**
 * Every ordered unit is reserved: the order can join a wave.
 */
final readonly class OrderAllocated implements DomainEvent
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
