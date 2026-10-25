<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\Event\OrderShipped;
use App\Outbound\Domain\OrderLine;
use App\Outbound\Domain\OrderStatus;
use App\Outbound\Domain\ReservationId;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\TestCase;

/**
 * What the dispatch does to an order (OUT-03, OUT-04, OUT-25).
 */
final class OrderShippingTest extends TestCase
{
    use AssertsDomainErrors;

    public function testShippingGivesTheOrderTheUnitsItPicked(): void
    {
        $order = OutboundMother::pickedOrder([10, 4]);

        $order->recordShipment(OutboundMother::at());

        self::assertSame(OrderStatus::Shipped, $order->status());
        self::assertSame([10, 4], array_map(static fn (OrderLine $line): int => $line->shippedUnits, $order->lines()));
        $events = $order->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderShipped::class, $events[0]);
    }

    // OUT-03: shipped never exceeds picked
    public function testWhatWasNotFoundIsNotShipped(): void
    {
        $order = OutboundMother::pickedOrder([10, 4], [1 => 3]);

        $order->recordShipment(OutboundMother::at());

        self::assertSame([7, 4], array_map(static fn (OrderLine $line): int => $line->shippedUnits, $order->lines()));
        foreach ($order->lines() as $line) {
            self::assertSame($line->pickedUnits, $line->shippedUnits);
        }
    }

    public function testShippingReturnsTheReservationsOfTheLinesForTheStockToConsume(): void
    {
        $order = OutboundMother::pickedOrder([10, 4]);
        $expected = array_map(static fn (OrderLine $line): ?string => $line->reservationId?->value, $order->lines());

        $reservations = $order->recordShipment(OutboundMother::at());

        self::assertSame($expected, array_map(static fn (ReservationId $id): string => $id->value, $reservations));
    }

    // OUT-25
    public function testAnOrderIsShippedOnce(): void
    {
        $order = OutboundMother::pickedOrder([10]);
        $order->recordShipment(OutboundMother::at());

        self::assertDomainError('outbound.order_already_shipped', ['reference' => 'PED-2027-0001'], static fn (): array => $order->recordShipment(OutboundMother::at()));
    }

    // OUT-25
    public function testOnlyAPickedOrderCanBeShipped(): void
    {
        $order = OutboundMother::orderInWave([10]);

        self::assertDomainError('outbound.order_not_picked', ['reference' => 'PED-2027-0001', 'status' => 'in_wave'], static fn (): array => $order->recordShipment(OutboundMother::at()));
        self::assertSame(OrderStatus::InWave, $order->status());
    }

    // OUT-04: the goods are gone, so there is nothing to cancel
    public function testAShippedOrderCannotBeCancelled(): void
    {
        $order = OutboundMother::pickedOrder([10]);
        $order->recordShipment(OutboundMother::at());

        self::assertDomainError('outbound.order_already_shipped', ['reference' => 'PED-2027-0001'], static fn (): array => $order->cancel(OutboundMother::at()));
        self::assertSame(OrderStatus::Shipped, $order->status());
    }
}
