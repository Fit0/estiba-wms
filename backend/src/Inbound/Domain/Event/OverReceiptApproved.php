<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Event;

use App\Inbound\Domain\ReceiptId;
use App\Shared\Domain\DomainEvent;

/**
 * A supervisor accepted that the receipt holds more than the ASN announced (INB-08).
 */
final readonly class OverReceiptApproved implements DomainEvent
{
    public function __construct(
        public ReceiptId $receiptId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
