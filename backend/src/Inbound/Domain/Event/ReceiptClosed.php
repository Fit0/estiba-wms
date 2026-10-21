<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Event;

use App\Inbound\Domain\AsnId;
use App\Inbound\Domain\ReceiptId;
use App\Shared\Domain\DomainEvent;

/**
 * Counting finished. Raised once per receipt (INB-09). The stock is booked from it (E03-04).
 */
final readonly class ReceiptClosed implements DomainEvent
{
    public function __construct(
        public ReceiptId $receiptId,
        public AsnId $asnId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
