<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Event;

use App\Inbound\Domain\LocationId;
use App\Inbound\Domain\PutawayTaskId;
use App\Inbound\Domain\ReceiptId;
use App\Shared\Domain\DomainEvent;

/**
 * Goods at the dock wait to be put away, at the suggested location if a strategy found one.
 */
final readonly class PutawayTaskPlanned implements DomainEvent
{
    public function __construct(
        public PutawayTaskId $taskId,
        public ReceiptId $receiptId,
        public ?LocationId $suggestedLocationId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
