<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * One product of an order, how many units were ordered, how many are reserved for it and how many
 * have been picked.
 *
 * Allocated never exceeds ordered, picked never exceeds allocated, and shipped never exceeds picked
 * (OUT-03). The reservation that holds the units is Inventory's: the line only remembers its
 * identifier, so that cancelling the order can release it.
 */
final readonly class OrderLine
{
    public function __construct(
        public int $lineNumber,
        public ProductId $productId,
        public int $orderedUnits,
        public int $allocatedUnits = 0,
        public ?ReservationId $reservationId = null,
        public int $pickedUnits = 0,
        public int $shippedUnits = 0,
    ) {
    }

    public function withAllocation(ReservationId $reservationId, int $allocatedUnits): self
    {
        return new self($this->lineNumber, $this->productId, $this->orderedUnits, $allocatedUnits, $reservationId, $this->pickedUnits, $this->shippedUnits);
    }

    /**
     * Units were picked for the line, and some that were allocated were not found: those are no longer
     * allocated, so they are part of the shortage again.
     */
    public function withPick(int $picked, int $notFound): self
    {
        return new self($this->lineNumber, $this->productId, $this->orderedUnits, $this->allocatedUnits - $notFound, $this->reservationId, $this->pickedUnits + $picked, $this->shippedUnits);
    }

    /**
     * The goods left the warehouse: everything that was picked is shipped, and nothing else (OUT-03).
     */
    public function withShipment(): self
    {
        return new self($this->lineNumber, $this->productId, $this->orderedUnits, $this->allocatedUnits, $this->reservationId, $this->pickedUnits, $this->pickedUnits);
    }

    /**
     * The units that no stock covers yet.
     */
    public function shortageUnits(): int
    {
        return $this->orderedUnits - $this->allocatedUnits;
    }

    public function isFullyAllocated(): bool
    {
        return $this->allocatedUnits === $this->orderedUnits;
    }

    /**
     * Everything that is allocated has been picked.
     */
    public function isFullyPicked(): bool
    {
        return $this->pickedUnits === $this->allocatedUnits;
    }
}
