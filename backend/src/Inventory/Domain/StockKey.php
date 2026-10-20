<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

/**
 * What tells one stock item from another: the product, where it is, which lot and expiry date,
 * and its status. Two units with the same key are in the same stock item (DD-1).
 *
 * The expiry date is a day, not a moment.
 */
final readonly class StockKey
{
    public ?\DateTimeImmutable $expiryDate;

    public function __construct(
        public ProductId $productId,
        public LocationId $locationId,
        public ?LotNumber $lot,
        ?\DateTimeImmutable $expiryDate,
        public StockStatus $status,
    ) {
        $this->expiryDate = $expiryDate?->setTime(0, 0);
    }

    public function equals(self $other): bool
    {
        return $this->productId->equals($other->productId)
            && $this->locationId->equals($other->locationId)
            && $this->lot?->value === $other->lot?->value
            && $this->expiryDate?->format('Y-m-d') === $other->expiryDate?->format('Y-m-d')
            && $this->status === $other->status;
    }
}
