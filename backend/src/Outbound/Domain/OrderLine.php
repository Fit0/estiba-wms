<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * One product of an order, how many units were ordered, how many are reserved for it and how many
 * have been picked.
 *
 * Allocated never exceeds ordered, and picked never exceeds allocated (OUT-03). Shipped quantities arrive
 * with shipping. The reservation that holds the units is Inventory's: the line only remembers its
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
    ) {
    }

    public function withAllocation(ReservationId $reservationId, int $allocatedUnits): self
    {
        return new self($this->lineNumber, $this->productId, $this->orderedUnits, $allocatedUnits, $reservationId, $this->pickedUnits);
    }

    /**
     * Units were picked for the line, and some that were allocated were not found: those are no longer
     * allocated, so they are part of the shortage again.
     */
    public function withPick(int $picked, int $notFound): self
    {
        return new self($this->lineNumber, $this->productId, $this->orderedUnits, $this->allocatedUnits - $notFound, $this->reservationId, $this->pickedUnits + $picked);
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
