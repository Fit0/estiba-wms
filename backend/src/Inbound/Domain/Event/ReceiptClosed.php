<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Event;

use App\Inbound\Domain\AsnId;
use App\Inbound\Domain\OperatorId;
use App\Inbound\Domain\ReceiptId;
use App\Shared\Domain\DomainEvent;

/**
 * Counting finished. Raised once per receipt (INB-09). Its policies book the stock at the dock
 * and plan the putaway (ADR 0011).
 */
final readonly class ReceiptClosed implements DomainEvent
{
    public function __construct(
        public ReceiptId $receiptId,
        public AsnId $asnId,
        public OperatorId $closedBy,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
