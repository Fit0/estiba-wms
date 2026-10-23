<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Outbound\Domain\Event\OrderAllocated;
use App\Outbound\Domain\Event\OrderCancelled;
use App\Outbound\Domain\Event\OrderRegistered;
use App\Outbound\Domain\Exception\AllocationExceedsOrdered;
use App\Outbound\Domain\Exception\BackorderNotApplicable;
use App\Outbound\Domain\Exception\InvalidOrderedQuantity;
use App\Outbound\Domain\Exception\InvalidOrderLines;
use App\Outbound\Domain\Exception\OrderAlreadyCancelled;
use App\Outbound\Domain\Exception\OrderAlreadyInWave;
use App\Outbound\Domain\Exception\OrderLineNotFound;
use App\Outbound\Domain\Exception\OrderNotWaveable;
use App\Outbound\Domain\Exception\OrderReferenceAlreadyUsed;
use App\Outbound\Domain\Exception\ProductNotOrderable;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;

/**
 * What a customer asked to be sent: the products and units, where to, how urgently and by when.
 *
 * Stock is promised to the order line by line. An order can leave with less than it asked for
 * only if someone accepts the backorder, which waves will decide. Until then a partly covered
 * order waits for the rest, and allocating it again tops it up.
 */
final class Order extends AggregateRoot
{
    private OrderStatus $status = OrderStatus::Registered;

    /** The customer accepts to receive less than ordered. A partly covered order can then go to the floor (OUT-06). */
    private bool $backorderAccepted = false;

    /** The one wave the order belongs to, if any (OUT-05). */
    private ?WaveId $waveId = null;

    /**
     * @param non-empty-list<OrderLine> $lines
     */
    private function __construct(
        private readonly OrderId $id,
        private readonly TenantId $tenantId,
        private readonly OrderReference $reference,
        private readonly WarehouseId $warehouseId,
        private readonly CustomerName $customer,
        private readonly ShippingAddress $shipTo,
        private readonly OrderPriority $priority,
        private readonly \DateTimeImmutable $cutOffAt,
        private readonly \DateTimeImmutable $registeredAt,
        private array $lines,
    ) {
    }

    /**
     * @param list<OrderedItem> $items
     *
     * @throws OrderReferenceAlreadyUsed OUT-01
     * @throws InvalidOrderLines         OUT-02
     * @throws InvalidOrderedQuantity    OUT-02
     * @throws ProductNotOrderable       OUT-02
     */
    public static function register(
        OrderId $id,
        TenantId $tenantId,
        OrderReference $reference,
        WarehouseId $warehouseId,
        CustomerName $customer,
        ShippingAddress $shipTo,
        OrderPriority $priority,
        \DateTimeImmutable $cutOffAt,
        array $items,
        OrderReferenceRegistry $registry,
        \DateTimeImmutable $at,
    ): self {
        if ($registry->isReferenceTaken($tenantId, $reference)) {
            throw OrderReferenceAlreadyUsed::forReference($reference);
        }

        $lines = self::linesOf($items);
        $order = new self($id, $tenantId, $reference, $warehouseId, $customer, $shipTo, $priority, $cutOffAt, $at, $lines);
        $order->record(new OrderRegistered($id, $tenantId, $reference, $at));

        return $order;
    }

    /**
     * Records what the stock gave to some lines. The order becomes allocated when every line is
     * fully covered, and partially allocated while some units are covered.
     *
     * @param list<LineAllocation> $allocations
     *
     * @throws OrderAlreadyCancelled    OUT-04
     * @throws OrderAlreadyInWave       OUT-07
     * @throws OrderLineNotFound
     * @throws AllocationExceedsOrdered OUT-03
     */
    public function recordAllocations(array $allocations, \DateTimeImmutable $at): void
    {
        $this->assertNotCancelled();
        $this->assertNotInWave();

        // Everything is checked before anything changes, so a refused allocation leaves no trace.
        $lines = $this->lines;
        foreach ($allocations as $allocation) {
            $position = $this->positionOf($allocation->lineNumber);
            $line = $lines[$position];

            if ($allocation->allocatedUnits > $line->orderedUnits) {
                throw AllocationExceedsOrdered::forLine($line->lineNumber, $allocation->allocatedUnits, $line->orderedUnits);
            }

            $lines[$position] = $line->withAllocation($allocation->reservationId, $allocation->allocatedUnits);
        }

        $wasAllocated = OrderStatus::Allocated === $this->status;
        $this->lines = array_values($lines);
        $this->status = $this->statusOfLines();

        if (OrderStatus::Allocated === $this->status && !$wasAllocated) {
            $this->record(new OrderAllocated($this->id, $this->tenantId, $at));
        }
    }

    /**
     * Cancels the order. It returns the reservations that held stock for it, for the caller to
     * release (OUT-04). A shipped order cannot be cancelled either; shipping arrives later.
     *
     * @return list<ReservationId>
     *
     * @throws OrderAlreadyCancelled OUT-04
     */
    public function cancel(\DateTimeImmutable $at): array
    {
        $this->assertNotCancelled();

        $reservations = [];
        foreach ($this->lines as $line) {
            if ($line->reservationId instanceof ReservationId) {
                $reservations[] = $line->reservationId;
            }
        }

        $this->status = OrderStatus::Cancelled;
        $this->waveId = null;
        $this->record(new OrderCancelled($this->id, $this->tenantId, $at));

        return $reservations;
    }

    /**
     * The customer accepts to receive less than ordered, so the covered part can go out. Only an
     * order that is partly covered has a backorder to accept. Accepting twice changes nothing.
     *
     * @throws OrderAlreadyCancelled  OUT-04
     * @throws BackorderNotApplicable
     */
    public function acceptBackorder(): void
    {
        $this->assertNotCancelled();

        if (OrderStatus::PartiallyAllocated !== $this->status) {
            throw BackorderNotApplicable::forStatus($this->status);
        }

        $this->backorderAccepted = true;
    }

    /**
     * The order joins a wave. It must be fully allocated, or partly allocated with the backorder
     * accepted (OUT-06), and it can be in one wave only (OUT-05).
     *
     * @throws OrderAlreadyCancelled OUT-04
     * @throws OrderAlreadyInWave    OUT-05
     * @throws OrderNotWaveable      OUT-06
     */
    public function joinWave(WaveId $waveId): void
    {
        $this->assertNotCancelled();
        $this->assertNotInWave();

        $waveable = OrderStatus::Allocated === $this->status
            || (OrderStatus::PartiallyAllocated === $this->status && $this->backorderAccepted);
        if (!$waveable) {
            throw OrderNotWaveable::forStatus($this->status, $this->backorderAccepted);
        }

        $this->waveId = $waveId;
        $this->status = OrderStatus::InWave;
    }

    /**
     * The order leaves its wave, such as when the wave is cancelled, and is what it was before it
     * joined: allocated or partly allocated. Leaving when it is in no wave changes nothing.
     */
    public function leaveWave(): void
    {
        if (!$this->waveId instanceof WaveId) {
            return;
        }

        $this->waveId = null;
        $this->status = $this->statusOfLines();
    }

    /**
     * @throws OrderAlreadyInWave OUT-05
     */
    public function assertNotInWave(): void
    {
        if ($this->waveId instanceof WaveId) {
            throw OrderAlreadyInWave::forReference($this->reference);
        }
    }

    /**
     * @throws OrderAlreadyCancelled OUT-04
     */
    public function assertNotCancelled(): void
    {
        if (OrderStatus::Cancelled === $this->status) {
            throw OrderAlreadyCancelled::forReference($this->reference);
        }
    }

    /**
     * The units still without stock, in all lines.
     */
    public function shortageUnits(): int
    {
        return array_sum(array_map(static fn (OrderLine $line): int => $line->shortageUnits(), $this->lines));
    }

    public function id(): OrderId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function reference(): OrderReference
    {
        return $this->reference;
    }

    public function warehouseId(): WarehouseId
    {
        return $this->warehouseId;
    }

    public function customer(): CustomerName
    {
        return $this->customer;
    }

    public function shipTo(): ShippingAddress
    {
        return $this->shipTo;
    }

    public function priority(): OrderPriority
    {
        return $this->priority;
    }

    public function cutOffAt(): \DateTimeImmutable
    {
        return $this->cutOffAt;
    }

    public function registeredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function waveId(): ?WaveId
    {
        return $this->waveId;
    }

    public function backorderAccepted(): bool
    {
        return $this->backorderAccepted;
    }

    /**
     * @return non-empty-list<OrderLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @param list<OrderedItem> $items
     *
     * @return non-empty-list<OrderLine>
     */
    private static function linesOf(array $items): array
    {
        if ([] === $items) {
            throw InvalidOrderLines::none();
        }
        if (\count($items) > InvalidOrderLines::MAX_LINES) {
            throw InvalidOrderLines::tooMany(\count($items));
        }

        $lines = [];
        foreach ($items as $position => $item) {
            $lineNumber = $position + 1;

            if ($item->units < 1) {
                throw InvalidOrderedQuantity::forLine($lineNumber, $item->units);
            }
            if (!$item->product->available) {
                throw ProductNotOrderable::unavailable($item->product->id->value);
            }

            $lines[] = new OrderLine($lineNumber, $item->product->id, $item->units);
        }

        return $lines;
    }

    private function positionOf(int $lineNumber): int
    {
        foreach ($this->lines as $position => $line) {
            if ($line->lineNumber === $lineNumber) {
                return $position;
            }
        }

        throw OrderLineNotFound::number($lineNumber);
    }

    private function statusOfLines(): OrderStatus
    {
        $covered = false;
        $complete = true;

        foreach ($this->lines as $line) {
            $covered = $covered || $line->allocatedUnits > 0;
            $complete = $complete && $line->isFullyAllocated();
        }

        return match (true) {
            $complete => OrderStatus::Allocated,
            $covered => OrderStatus::PartiallyAllocated,
            default => OrderStatus::Registered,
        };
    }
}
