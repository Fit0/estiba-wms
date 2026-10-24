<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\Event\WaveCancelled;
use App\Outbound\Domain\Event\WaveCompleted;
use App\Outbound\Domain\Event\WaveCreated;
use App\Outbound\Domain\Event\WaveReleased;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\PickListId;
use App\Outbound\Domain\PickListStatus;
use App\Outbound\Domain\Wave;
use App\Outbound\Domain\WaveId;
use App\Outbound\Domain\WaveStatus;
use App\Shared\Domain\DomainEvent;
use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

final class WaveTest extends TestCase
{
    use AssertsDomainErrors;

    public function testPlansAWaveWithItsOrders(): void
    {
        $first = OutboundMother::order(reference: 'PED-1');
        $second = OutboundMother::order(tenantId: $first->tenantId(), reference: 'PED-2', warehouseId: $first->warehouseId());

        $wave = $this->plan([$first, $second], $first);

        self::assertSame(WaveStatus::Planned, $wave->status());
        self::assertSame([$first->id()->value, $second->id()->value], array_map(static fn (OrderId $id): string => $id->value, $wave->orderIds()));
        self::assertSame([], $wave->pickListIds());
        self::assertNull($wave->releasedAt());
        self::assertEquals(OutboundMother::at(), $wave->createdAt());
        self::assertCount(1, array_filter($wave->pullEvents(), static fn (DomainEvent $event): bool => $event instanceof WaveCreated));
    }

    public function testTakesTheWarehouseOfItsOrders(): void
    {
        $order = OutboundMother::order();

        self::assertTrue($order->warehouseId()->equals($this->plan([$order], $order)->warehouseId()));
    }

    public function testAWaveNeedsAtLeastOneOrder(): void
    {
        self::assertDomainError('outbound.wave_orders_invalid', ['reason' => 'empty', 'maxOrders' => 200], static fn (): Wave => Wave::plan(WaveId::fromString(Ids::next()), OutboundMother::tenantId(), [], OutboundMother::at()));
    }

    public function testAWaveHoldsAtMostTwoHundredOrders(): void
    {
        $orders = array_map(static fn (int $i): Order => OutboundMother::order(reference: 'PED-'.$i), range(1, 201));

        self::assertDomainError('outbound.wave_orders_invalid', ['reason' => 'too_many', 'maxOrders' => 200], static fn (): Wave => Wave::plan(WaveId::fromString(Ids::next()), OutboundMother::tenantId(), $orders, OutboundMother::at()));
    }

    public function testTheSameOrderCannotBeInTheWaveTwice(): void
    {
        $order = OutboundMother::order();

        self::assertDomainError('outbound.wave_orders_invalid', ['reason' => 'repeated', 'maxOrders' => 200], fn (): Wave => $this->plan([$order, $order], $order));
    }

    // OUT-14
    public function testAllTheOrdersAreServedFromTheSameWarehouse(): void
    {
        $first = OutboundMother::order(reference: 'PED-1');
        $elsewhere = OutboundMother::order(reference: 'PED-2');

        self::assertDomainError('outbound.wave_warehouse_mismatch', [], fn (): Wave => $this->plan([$first, $elsewhere], $first));
    }

    // OUT-07
    public function testAnOrderCanLeaveAWaveThatIsStillPlanned(): void
    {
        $first = OutboundMother::order(reference: 'PED-1');
        $wave = $this->plan([$first], $first);

        $wave->removeOrder($first->id());

        self::assertSame([], $wave->orderIds());
    }

    public function testRemovingAnOrderThatIsNotInTheWaveChangesNothing(): void
    {
        $order = OutboundMother::order();
        $wave = $this->plan([$order], $order);

        $wave->removeOrder(OrderId::fromString(Ids::next()));

        self::assertCount(1, $wave->orderIds());
    }

    public function testReleasingPutsTheWaveOnTheFloorWithItsPickLists(): void
    {
        $order = OutboundMother::order();
        $wave = $this->plan([$order], $order);
        $wave->pullEvents();
        $lists = [PickListId::fromString(Ids::next()), PickListId::fromString(Ids::next())];

        $wave->release($lists, OutboundMother::at());

        self::assertSame(WaveStatus::Released, $wave->status());
        self::assertEquals(OutboundMother::at(), $wave->releasedAt());
        self::assertSame([$lists[0]->value, $lists[1]->value], array_map(static fn (PickListId $id): string => $id->value, $wave->pickListIds()));
        $events = $wave->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(WaveReleased::class, $events[0]);
    }

    public function testAWaveWithNoOrdersLeftCannotBeReleased(): void
    {
        $order = OutboundMother::order();
        $wave = $this->plan([$order], $order);
        $wave->removeOrder($order->id());

        self::assertDomainError('outbound.wave_orders_invalid', ['reason' => 'empty', 'maxOrders' => 200], static fn () => $wave->assertReleasable());
        self::assertDomainError('outbound.wave_orders_invalid', ['reason' => 'empty', 'maxOrders' => 200], static fn () => $wave->release([PickListId::fromString(Ids::next())], OutboundMother::at()));
    }

    public function testReleasingWithoutPickListsIsRefused(): void
    {
        $order = OutboundMother::order();
        $wave = $this->plan([$order], $order);

        self::assertDomainError('outbound.pick_list_invalid', ['reason' => 'empty'], static fn () => $wave->release([], OutboundMother::at()));
        self::assertSame(WaveStatus::Planned, $wave->status());
    }

    // OUT-07
    public function testOnceReleasedTheOrdersCannotChangeAndTheWaveCannotBeReleasedAgain(): void
    {
        $order = OutboundMother::order();
        $wave = $this->plan([$order], $order);
        $wave->release([PickListId::fromString(Ids::next())], OutboundMother::at());

        self::assertDomainError('outbound.wave_already_released', ['id' => $wave->id()->value], static fn () => $wave->removeOrder($order->id()));
        self::assertDomainError('outbound.wave_already_released', ['id' => $wave->id()->value], static fn () => $wave->release([PickListId::fromString(Ids::next())], OutboundMother::at()));
        self::assertCount(1, $wave->orderIds());
    }

    public function testCancellingGivesBackTheOrdersWhetherPlannedOrReleased(): void
    {
        $order = OutboundMother::order();
        $planned = $this->plan([$order], $order);
        $planned->pullEvents();

        $orderIds = $planned->cancel(OutboundMother::at());

        self::assertSame(WaveStatus::Cancelled, $planned->status());
        self::assertSame([$order->id()->value], array_map(static fn (OrderId $id): string => $id->value, $orderIds));
        self::assertInstanceOf(WaveCancelled::class, $planned->pullEvents()[0]);

        $released = $this->plan([$order], $order);
        $released->release([PickListId::fromString(Ids::next())], OutboundMother::at());
        $released->cancel(OutboundMother::at());
        self::assertSame(WaveStatus::Cancelled, $released->status());
    }

    public function testACancelledWaveIsFinished(): void
    {
        $order = OutboundMother::order();
        $wave = $this->plan([$order], $order);
        $wave->cancel(OutboundMother::at());
        $error = ['id' => $wave->id()->value];

        self::assertDomainError('outbound.wave_already_cancelled', $error, static fn (): array => $wave->cancel(OutboundMother::at()));
        self::assertDomainError('outbound.wave_already_cancelled', $error, static fn () => $wave->assertReleasable());
        self::assertDomainError('outbound.wave_already_cancelled', $error, static fn () => $wave->removeOrder($order->id()));
    }

    // OUT-08
    public function testIsCompleteWhenEveryPickListIsCompletedOrCancelled(): void
    {
        $wave = $this->releasedWave();
        $wave->pullEvents();

        $wave->completeWhenDone([PickListStatus::Completed, PickListStatus::Cancelled, PickListStatus::Completed], new \DateTimeImmutable('2026-10-25T12:00:00+00:00'));

        self::assertSame(WaveStatus::Completed, $wave->status());
        self::assertEquals(new \DateTimeImmutable('2026-10-25T12:00:00+00:00'), $wave->completedAt());
        self::assertInstanceOf(WaveCompleted::class, $wave->pullEvents()[0]);
    }

    // OUT-08
    public function testIsNotCompleteWhileAPickListIsStillOpen(): void
    {
        $wave = $this->releasedWave();

        foreach ([PickListStatus::Generated, PickListStatus::InProgress] as $open) {
            $wave->completeWhenDone([PickListStatus::Completed, $open], OutboundMother::at());
            self::assertSame(WaveStatus::Released, $wave->status());
        }
    }

    public function testAWaveWhoseListsAreAllCancelledIsNotCompleted(): void
    {
        $wave = $this->releasedWave();

        $wave->completeWhenDone([PickListStatus::Cancelled, PickListStatus::Cancelled], OutboundMother::at());

        self::assertSame(WaveStatus::Released, $wave->status());
    }

    public function testAWaveThatIsNotOnTheFloorIsNotCompleted(): void
    {
        $order = OutboundMother::order();
        $planned = $this->plan([$order], $order);

        $planned->completeWhenDone([PickListStatus::Completed], OutboundMother::at());

        self::assertSame(WaveStatus::Planned, $planned->status());
    }

    public function testNoPickListsIsNotDone(): void
    {
        $wave = $this->releasedWave();

        $wave->completeWhenDone([], OutboundMother::at());

        self::assertSame(WaveStatus::Released, $wave->status());
    }

    public function testACompletedWaveIsFinished(): void
    {
        $wave = $this->releasedWave();
        $wave->completeWhenDone([PickListStatus::Completed], OutboundMother::at());
        $error = ['id' => $wave->id()->value];

        self::assertDomainError('outbound.wave_already_completed', $error, static fn (): array => $wave->cancel(OutboundMother::at()));
        self::assertDomainError('outbound.wave_already_completed', $error, static fn () => $wave->assertReleasable());
    }

    private function releasedWave(): Wave
    {
        $order = OutboundMother::order();
        $wave = $this->plan([$order], $order);
        $wave->release([PickListId::fromString(Ids::next())], OutboundMother::at());

        return $wave;
    }

    /**
     * @param list<Order> $orders
     */
    private function plan(array $orders, Order $basis): Wave
    {
        return Wave::plan(WaveId::fromString(Ids::next()), $basis->tenantId(), $orders, OutboundMother::at());
    }
}
