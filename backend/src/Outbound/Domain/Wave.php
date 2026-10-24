<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Outbound\Domain\Event\WaveCancelled;
use App\Outbound\Domain\Event\WaveCompleted;
use App\Outbound\Domain\Event\WaveCreated;
use App\Outbound\Domain\Event\WaveReleased;
use App\Outbound\Domain\Exception\InvalidPickList;
use App\Outbound\Domain\Exception\InvalidWaveOrders;
use App\Outbound\Domain\Exception\WaveAlreadyCancelled;
use App\Outbound\Domain\Exception\WaveAlreadyCompleted;
use App\Outbound\Domain\Exception\WaveAlreadyReleased;
use App\Outbound\Domain\Exception\WaveWarehouseMismatch;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;

/**
 * A group of orders released to the floor together, so the picking of many orders is planned as one
 * walk through the warehouse.
 *
 * The wave remembers its orders by identifier. The order remembers its wave: joining is the order's
 * decision, because only the order knows whether it is covered (OUT-06). While the wave is planned
 * an order can leave it. Once released, the pick lists exist and the orders cannot change (OUT-07).
 */
final class Wave extends AggregateRoot
{
    private WaveStatus $status = WaveStatus::Planned;

    private ?\DateTimeImmutable $releasedAt = null;

    private ?\DateTimeImmutable $completedAt = null;

    /** @var list<PickListId> */
    private array $pickListIds = [];

    /**
     * @param list<OrderId> $orderIds
     */
    private function __construct(
        private readonly WaveId $id,
        private readonly TenantId $tenantId,
        private readonly WarehouseId $warehouseId,
        private array $orderIds,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Plans a wave for these orders. They must all be served from the same warehouse, because a
     * pick list walks one (OUT-14). Whether each order may join is for the order to say, and the
     * caller asks it before planning.
     *
     * @param list<Order> $orders
     *
     * @throws InvalidWaveOrders
     * @throws WaveWarehouseMismatch OUT-14
     */
    public static function plan(WaveId $id, TenantId $tenantId, array $orders, \DateTimeImmutable $at): self
    {
        if ([] === $orders) {
            throw InvalidWaveOrders::none();
        }
        if (\count($orders) > InvalidWaveOrders::MAX_ORDERS) {
            throw InvalidWaveOrders::tooMany(\count($orders));
        }

        $orderIds = [];
        $warehouseId = $orders[0]->warehouseId();
        foreach ($orders as $order) {
            if (!$order->warehouseId()->equals($warehouseId)) {
                throw WaveWarehouseMismatch::between();
            }
            foreach ($orderIds as $known) {
                if ($known->equals($order->id())) {
                    throw InvalidWaveOrders::repeated($order->id()->value);
                }
            }

            $orderIds[] = $order->id();
        }

        $wave = new self($id, $tenantId, $warehouseId, $orderIds, $at);
        $wave->record(new WaveCreated($id, $tenantId, $at));

        return $wave;
    }

    /**
     * An order leaves the wave, because it was cancelled. Only while the wave is planned (OUT-07).
     * An order that is not in the wave changes nothing.
     *
     * @throws WaveAlreadyReleased  OUT-07
     * @throws WaveAlreadyCancelled
     */
    public function removeOrder(OrderId $orderId): void
    {
        $this->assertPlanned();

        $this->orderIds = array_values(array_filter($this->orderIds, static fn (OrderId $known): bool => !$known->equals($orderId)));
    }

    /**
     * Whether the wave can go to the floor now: it is planned and has orders.
     *
     * @throws WaveAlreadyReleased
     * @throws WaveAlreadyCancelled
     * @throws InvalidWaveOrders    when every order has left
     */
    public function assertReleasable(): void
    {
        $this->assertPlanned();

        if ([] === $this->orderIds) {
            throw InvalidWaveOrders::none();
        }
    }

    /**
     * Sends the wave to the floor with the pick lists that were made for it.
     *
     * @param list<PickListId> $pickListIds
     *
     * @throws WaveAlreadyReleased  OUT-07
     * @throws WaveAlreadyCancelled
     * @throws InvalidWaveOrders    when every order has left
     * @throws InvalidPickList      when there is no pick list
     */
    public function release(array $pickListIds, \DateTimeImmutable $at): void
    {
        $this->assertReleasable();

        if ([] === $pickListIds) {
            throw InvalidPickList::noLines();
        }

        $this->status = WaveStatus::Released;
        $this->releasedAt = $at;
        $this->pickListIds = $pickListIds;
        $this->record(new WaveReleased($this->id, $this->tenantId, $at));
    }

    /**
     * The wave is complete when every one of its pick lists is completed or cancelled, and one at
     * least is completed (OUT-08). It is asked after each list that finishes, and a wave that is not
     * on the floor, or not done yet, stays as it is.
     *
     * @param list<PickListStatus> $pickLists the status of every pick list of the wave
     */
    public function completeWhenDone(array $pickLists, \DateTimeImmutable $at): void
    {
        if (WaveStatus::Released !== $this->status || [] === $pickLists) {
            return;
        }

        $anyCompleted = false;
        foreach ($pickLists as $status) {
            if (PickListStatus::Completed === $status) {
                $anyCompleted = true;

                continue;
            }
            if (PickListStatus::Cancelled !== $status) {
                return;
            }
        }

        if ($anyCompleted) {
            $this->status = WaveStatus::Completed;
            $this->completedAt = $at;
            $this->record(new WaveCompleted($this->id, $this->tenantId, $at));
        }
    }

    /**
     * Calls the wave off, planned or on the floor. It returns its orders, for the caller to free.
     *
     * @return list<OrderId>
     *
     * @throws WaveAlreadyCancelled
     */
    public function cancel(\DateTimeImmutable $at): array
    {
        if (WaveStatus::Cancelled === $this->status) {
            throw WaveAlreadyCancelled::forId($this->id->value);
        }
        if (WaveStatus::Completed === $this->status) {
            throw WaveAlreadyCompleted::forId($this->id->value);
        }

        $orderIds = $this->orderIds;
        $this->status = WaveStatus::Cancelled;
        $this->record(new WaveCancelled($this->id, $this->tenantId, $at));

        return $orderIds;
    }

    public function id(): WaveId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function warehouseId(): WarehouseId
    {
        return $this->warehouseId;
    }

    public function status(): WaveStatus
    {
        return $this->status;
    }

    /**
     * @return list<OrderId>
     */
    public function orderIds(): array
    {
        return $this->orderIds;
    }

    /**
     * @return list<PickListId>
     */
    public function pickListIds(): array
    {
        return $this->pickListIds;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function releasedAt(): ?\DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    private function assertPlanned(): void
    {
        match ($this->status) {
            WaveStatus::Released => throw WaveAlreadyReleased::forId($this->id->value),
            WaveStatus::Cancelled => throw WaveAlreadyCancelled::forId($this->id->value),
            WaveStatus::Completed => throw WaveAlreadyCompleted::forId($this->id->value),
            WaveStatus::Planned => null,
        };
    }
}
