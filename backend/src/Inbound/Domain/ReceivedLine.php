<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

/**
 * One count at the dock: this many base units of a product, of this lot and expiry date, in this
 * condition. Lines are numbered from 1 in the order they were received.
 */
final readonly class ReceivedLine
{
    public ?\DateTimeImmutable $expiryDate;

    public function __construct(
        public int $lineNumber,
        public ProductId $productId,
        public int $units,
        public ?LotNumber $lot,
        ?\DateTimeImmutable $expiryDate,
        public ItemCondition $condition,
        public \DateTimeImmutable $receivedAt,
    ) {
        $this->expiryDate = $expiryDate?->setTime(0, 0);
    }
}
