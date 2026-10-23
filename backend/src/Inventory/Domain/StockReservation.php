<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Exception\AllocationExceedsRequested;
use App\Inventory\Domain\Exception\ReservationNotActive;
use App\Inventory\Domain\Exception\ReservationQuantityNotPositive;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;

/**
 * Units of a product promised to one demand, such as an order line, and the stock items that
 * cover them.
 *
 * It can be covered in part: the units that no stock item could give are the shortage, and a later
 * allocation can cover them when stock arrives. The reservation never asks for more than it was
 * opened for (STK-11).
 *
 * The stock items hold the promise as their `reserved` units. This aggregate remembers which ones
 * they are, so that releasing it gives each of them its units back.
 */
final class StockReservation extends AggregateRoot
{
    private ReservationStatus $status = ReservationStatus::Active;

    /** @var list<Allocation> */
    private array $allocations = [];

    private function __construct(
        private readonly ReservationId $id,
        private readonly TenantId $tenantId,
        private readonly ReservationReference $reference,
        private readonly ProductId $productId,
        private readonly Quantity $requested,
        private readonly AllocationStrategy $strategy,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @throws ReservationQuantityNotPositive STK-11
     */
    public static function open(
        ReservationId $id,
        TenantId $tenantId,
        ReservationReference $reference,
        ProductId $productId,
        Quantity $requested,
        AllocationStrategy $strategy,
        \DateTimeImmutable $at,
    ): self {
        if ($requested->isZero()) {
            throw ReservationQuantityNotPositive::ofZero();
        }

        return new self($id, $tenantId, $reference, $productId, $requested, $strategy, $at);
    }

    /**
     * Adds the stock items that now cover part of the reservation. A stock item that already
     * covers part of it has its units added to that part.
     *
     * @param list<Allocation> $allocations
     *
     * @throws ReservationNotActive       STK-13
     * @throws AllocationExceedsRequested STK-11
     */
    public function allocate(array $allocations): void
    {
        if (ReservationStatus::Active !== $this->status) {
            throw ReservationNotActive::forId($this->id);
        }

        $total = $this->allocated()->units;
        foreach ($allocations as $allocation) {
            $total += $allocation->quantity->units;
        }
        if ($total > $this->requested->units) {
            throw AllocationExceedsRequested::of($total, $this->requested->units);
        }

        foreach ($allocations as $allocation) {
            $this->allocations = $this->merged($this->allocations, $allocation);
        }
    }

    /**
     * Gives the units back. It returns the allocations that were holding units, for the caller to
     * free in their stock items. Releasing again returns nothing, so repeating it is safe (STK-14).
     *
     * @return list<Allocation>
     */
    public function release(): array
    {
        if (ReservationStatus::Released === $this->status) {
            return [];
        }

        $held = $this->allocations;
        $this->status = ReservationStatus::Released;
        $this->allocations = [];

        return $held;
    }

    /**
     * The units of the allocations, which is never more than was requested (STK-11).
     */
    public function allocated(): Quantity
    {
        return Quantity::of(array_sum(array_map(static fn (Allocation $allocation): int => $allocation->quantity->units, $this->allocations)));
    }

    /**
     * The units still to cover: the shortage.
     */
    public function missing(): Quantity
    {
        return $this->requested->minus($this->allocated());
    }

    public function id(): ReservationId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function reference(): ReservationReference
    {
        return $this->reference;
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function requested(): Quantity
    {
        return $this->requested;
    }

    public function strategy(): AllocationStrategy
    {
        return $this->strategy;
    }

    public function status(): ReservationStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return list<Allocation>
     */
    public function allocations(): array
    {
        return $this->allocations;
    }

    /**
     * @param list<Allocation> $allocations
     *
     * @return list<Allocation>
     */
    private function merged(array $allocations, Allocation $added): array
    {
        $merged = [];
        $found = false;

        foreach ($allocations as $allocation) {
            if ($allocation->stockItemId->equals($added->stockItemId)) {
                $allocation = new Allocation($allocation->stockItemId, $allocation->quantity->plus($added->quantity));
                $found = true;
            }

            $merged[] = $allocation;
        }

        if (!$found) {
            $merged[] = $added;
        }

        return $merged;
    }
}
