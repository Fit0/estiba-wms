<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * One product of an order, how many units were ordered and how many are reserved for it.
 *
 * Allocated never exceeds ordered (OUT-03). Picked and shipped quantities join it with picking and
 * shipping. The reservation that holds the units is Inventory's: the line only remembers its
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
    ) {
    }

    public function withAllocation(ReservationId $reservationId, int $allocatedUnits): self
    {
        return new self($this->lineNumber, $this->productId, $this->orderedUnits, $allocatedUnits, $reservationId);
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
}
