<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\LineAllocation;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderStatus;
use App\Outbound\Domain\WaveId;
use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

/**
 * How an order joins a wave and leaves it (OUT-05, OUT-06, OUT-07).
 */
final class OrderWaveTest extends TestCase
{
    use AssertsDomainErrors;

    // OUT-06
    public function testAnAllocatedOrderJoinsAWave(): void
    {
        $order = $this->allocatedOrder();
        $waveId = WaveId::fromString(Ids::next());

        $order->joinWave($waveId);

        self::assertSame(OrderStatus::InWave, $order->status());
        self::assertSame($waveId->value, $order->waveId()?->value);
    }

    // OUT-06
    public function testAnOrderThatIsNotAllocatedCannotJoin(): void
    {
        $order = OutboundMother::order();

        self::assertDomainError('outbound.order_not_waveable', ['status' => 'registered', 'backorderAccepted' => false], static fn () => $order->joinWave(WaveId::fromString(Ids::next())));
        self::assertSame(OrderStatus::Registered, $order->status());
        self::assertNull($order->waveId());
    }

    // OUT-06
    public function testAPartlyAllocatedOrderNeedsTheBackorderAccepted(): void
    {
        $order = $this->partlyAllocatedOrder();

        self::assertDomainError('outbound.order_not_waveable', ['status' => 'partially_allocated', 'backorderAccepted' => false], static fn () => $order->joinWave(WaveId::fromString(Ids::next())));

        $order->acceptBackorder();
        $order->joinWave(WaveId::fromString(Ids::next()));

        self::assertSame(OrderStatus::InWave, $order->status());
    }

    public function testOnlyAPartlyAllocatedOrderHasABackorderToAccept(): void
    {
        self::assertDomainError('outbound.backorder_not_applicable', ['status' => 'registered'], static fn () => OutboundMother::order()->acceptBackorder());
        self::assertDomainError('outbound.backorder_not_applicable', ['status' => 'allocated'], fn () => $this->allocatedOrder()->acceptBackorder());
    }

    public function testAcceptingTheBackorderTwiceChangesNothing(): void
    {
        $order = $this->partlyAllocatedOrder();

        $order->acceptBackorder();
        $order->acceptBackorder();

        self::assertTrue($order->backorderAccepted());
        self::assertSame(OrderStatus::PartiallyAllocated, $order->status());
    }

    // OUT-05
    public function testAnOrderBelongsToAtMostOneWave(): void
    {
        $order = $this->allocatedOrder();
        $order->joinWave(WaveId::fromString(Ids::next()));

        self::assertDomainError('outbound.order_already_in_wave', ['reference' => 'PED-2027-0001'], static fn () => $order->joinWave(WaveId::fromString(Ids::next())));
    }

    public function testACancelledOrderCannotJoinAWave(): void
    {
        $order = $this->allocatedOrder();
        $order->cancel(OutboundMother::at());

        self::assertDomainError('outbound.order_already_cancelled', ['reference' => 'PED-2027-0001'], static fn () => $order->joinWave(WaveId::fromString(Ids::next())));
    }

    public function testLeavingTheWaveReturnsAnAllocatedOrderToAllocated(): void
    {
        $order = $this->allocatedOrder();
        $order->joinWave(WaveId::fromString(Ids::next()));

        $order->leaveWave();

        self::assertSame(OrderStatus::Allocated, $order->status());
        self::assertNull($order->waveId());
    }

    public function testLeavingTheWaveReturnsAPartlyAllocatedOrderToPartiallyAllocated(): void
    {
        $order = $this->partlyAllocatedOrder();
        $order->acceptBackorder();
        $order->joinWave(WaveId::fromString(Ids::next()));

        $order->leaveWave();

        self::assertSame(OrderStatus::PartiallyAllocated, $order->status());
        self::assertTrue($order->backorderAccepted(), 'The customer still accepts it.');
    }

    public function testLeavingWhenInNoWaveChangesNothing(): void
    {
        $order = $this->allocatedOrder();

        $order->leaveWave();

        self::assertSame(OrderStatus::Allocated, $order->status());
    }

    // OUT-07: an order in a wave keeps the stock that was promised to it
    public function testAnOrderInAWaveCannotBeAllocatedAgain(): void
    {
        $order = $this->allocatedOrder();
        $order->joinWave(WaveId::fromString(Ids::next()));

        self::assertDomainError('outbound.order_already_in_wave', ['reference' => 'PED-2027-0001'], static fn () => $order->recordAllocations([new LineAllocation(1, OutboundMother::reservationId(), 5)], OutboundMother::at()));
        self::assertDomainError('outbound.order_already_in_wave', ['reference' => 'PED-2027-0001'], static fn () => $order->assertNotInWave());
    }

    public function testCancellingAnOrderTakesItOutOfItsWave(): void
    {
        $order = $this->allocatedOrder();
        $order->joinWave(WaveId::fromString(Ids::next()));

        $reservations = $order->cancel(OutboundMother::at());

        self::assertCount(1, $reservations);
        self::assertSame(OrderStatus::Cancelled, $order->status());
        self::assertNull($order->waveId());
    }

    private function allocatedOrder(): Order
    {
        $order = OutboundMother::order([OutboundMother::item(5)]);
        $order->recordAllocations([new LineAllocation(1, OutboundMother::reservationId(), 5)], OutboundMother::at());

        return $order;
    }

    private function partlyAllocatedOrder(): Order
    {
        $order = OutboundMother::order([OutboundMother::item(10)]);
        $order->recordAllocations([new LineAllocation(1, OutboundMother::reservationId(), 4)], OutboundMother::at());

        return $order;
    }
}
