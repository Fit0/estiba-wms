<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

/**
 * What a receipt brought of one product, lot and expiry date, in one condition: the units that
 * become one stock item at the dock and that putaway moves on. Damaged units go to quarantine
 * (INB-10).
 */
final readonly class ReceivedGoods
{
    public ?\DateTimeImmutable $expiryDate;

    public function __construct(
        public ProductId $productId,
        public int $units,
        public ?LotNumber $lot,
        ?\DateTimeImmutable $expiryDate,
        public bool $quarantine,
    ) {
        $this->expiryDate = $expiryDate?->setTime(0, 0);
    }

    public function withUnits(int $units): self
    {
        return new self($this->productId, $units, $this->lot, $this->expiryDate, $this->quarantine);
    }
}
