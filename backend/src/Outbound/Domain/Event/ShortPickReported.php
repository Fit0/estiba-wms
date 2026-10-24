<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Event;

use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\PickListId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

/**
 * The operator found fewer units than the list asked for. The units that were not found stop being promised to the order.
 */
final readonly class ShortPickReported implements DomainEvent
{
    public function __construct(
        public PickListId $pickListId,
        public int $position,
        public OrderId $orderId,
        public int $orderLineNumber,
        public int $missingUnits,
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
