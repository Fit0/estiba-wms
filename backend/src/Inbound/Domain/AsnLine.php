<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

/**
 * One product of an ASN: how many base units were announced and how many have arrived so far, in
 * the receipts that closed. A line never changes: receiving more makes a new one.
 */
final readonly class AsnLine
{
    public ?\DateTimeImmutable $expiryDate;

    public function __construct(
        public ProductId $productId,
        public int $expectedUnits,
        public int $receivedUnits,
        public ?LotNumber $lot,
        ?\DateTimeImmutable $expiryDate,
    ) {
        $this->expiryDate = $expiryDate?->setTime(0, 0);
    }

    public function withReceived(int $units): self
    {
        return new self($this->productId, $this->expectedUnits, $this->receivedUnits + $units, $this->lot, $this->expiryDate);
    }

    /**
     * The units still to come. Never below zero: receiving more than announced is an excess,
     * not a negative remainder.
     */
    public function remainingUnits(): int
    {
        return max(0, $this->expectedUnits - $this->receivedUnits);
    }

    public function isCovered(): bool
    {
        return $this->receivedUnits >= $this->expectedUnits;
    }
}
