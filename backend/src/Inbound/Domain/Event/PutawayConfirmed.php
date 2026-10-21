<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Event;

use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\LocationId;
use App\Inbound\Domain\OperatorId;
use App\Inbound\Domain\PutawayTaskId;
use App\Inbound\Domain\ReceivedGoods;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

/**
 * The goods are in their location. Its policy moves the stock from the dock (ADR 0011), so the
 * event carries everything the movement needs.
 */
final readonly class PutawayConfirmed implements DomainEvent
{
    public function __construct(
        public PutawayTaskId $taskId,
        public TenantId $tenantId,
        public ReceivedGoods $goods,
        public LocationId $fromLocationId,
        public LocationId $toLocationId,
        public AsnReference $reference,
        public OperatorId $confirmedBy,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
