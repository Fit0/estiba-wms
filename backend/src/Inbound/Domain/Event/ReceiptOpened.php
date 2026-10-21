<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Event;

use App\Inbound\Domain\AsnId;
use App\Inbound\Domain\LocationId;
use App\Inbound\Domain\ReceiptId;
use App\Shared\Domain\DomainEvent;

/**
 * An operator started counting a delivery at a dock.
 */
final readonly class ReceiptOpened implements DomainEvent
{
    public function __construct(
        public ReceiptId $receiptId,
        public AsnId $asnId,
        public LocationId $dockLocationId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
