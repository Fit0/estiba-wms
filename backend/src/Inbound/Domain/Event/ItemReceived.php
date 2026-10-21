<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Event;

use App\Inbound\Domain\ItemCondition;
use App\Inbound\Domain\ProductId;
use App\Inbound\Domain\ReceiptId;
use App\Shared\Domain\DomainEvent;

/**
 * An operator counted units of a product at the dock.
 */
final readonly class ItemReceived implements DomainEvent
{
    public function __construct(
        public ReceiptId $receiptId,
        public int $lineNumber,
        public ProductId $productId,
        public int $units,
        public ItemCondition $condition,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
