<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Event;

use App\Inbound\Domain\DiscrepancyType;
use App\Inbound\Domain\ProductId;
use App\Inbound\Domain\ReceiptId;
use App\Shared\Domain\DomainEvent;

/**
 * What arrived differs from the ASN: an excess, an unexpected product or damaged goods. A shortage is only known when the receipt closes.
 */
final readonly class DiscrepancyDetected implements DomainEvent
{
    public function __construct(
        public ReceiptId $receiptId,
        public ProductId $productId,
        public DiscrepancyType $type,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
