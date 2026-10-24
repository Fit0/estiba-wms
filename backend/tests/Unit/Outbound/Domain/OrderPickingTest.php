<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\Event\OrderPicked;
use App\Outbound\Domain\LineAllocation;
use App\Outbound\Domain\OrderStatus;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\TestCase;

/**
 * What picking does to an order (OUT-03, OUT-04).
 */
final class OrderPickingTest extends TestCase
{
    use AssertsDomainErrors;

    public function testPickingPartOfALineLeavesTheOrderInTheWave(): void
    {
        $order = OutboundMother::orderInWave([10]);

        $order->recordPick(1, 6, 0, OutboundMother::at());

        self::assertSame(6, $order->lines()[0]->pickedUnits);
        self::assertSame(OrderStatus::InWave, $order->status());
        self::assertSame([], $order->pullEvents());
    }

    public function testTheOrderIsPickedWhenEverythingAllocatedIsPicked(): void
    {
        $order = OutboundMother::orderInWave([10, 4]);
        $order->recordPick(1, 10, 0, OutboundMother::at());
        self::assertSame(OrderStatus::InWave, $order->status());

        $order->recordPick(2, 4, 0, OutboundMother::at());

        self::assertSame(OrderStatus::Picked, $order->status());
        $events = $order->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderPicked::class, $events[0]);
    }

    public function testASeveralStopsLineIsPickedInPieces(): void
    {
        $order = OutboundMother::orderInWave([10]);

        $order->recordPick(1, 6, 0, OutboundMother::at());
        $order->recordPick(1, 4, 0, OutboundMother::at());

        self::assertSame(10, $order->lines()[0]->pickedUnits);
        self::assertSame(OrderStatus::Picked, $order->status());
    }

    // OUT-03: what was not found is no longer allocated
    public function testUnitsNotFoundAreNoLongerAllocatedAndBecomeShortage(): void
    {
        $order = OutboundMother::orderInWave([10]);

        $order->recordPick(1, 7, 3, OutboundMother::at());

        $line = $order->lines()[0];
        self::assertSame(7, $line->pickedUnits);
        self::assertSame(7, $line->allocatedUnits);
        self::assertSame(3, $line->shortageUnits());
        self::assertSame(3, $order->shortageUnits());
        self::assertSame(OrderStatus::Picked, $order->status(), 'Everything that is still allocated has been picked.');
    }

    public function testALineWhereNothingWasFoundIsDoneAtZero(): void
    {
        $order = OutboundMother::orderInWave([5]);

        $order->recordPick(1, 0, 5, OutboundMother::at());

        self::assertSame(0, $order->lines()[0]->allocatedUnits);
        self::assertSame(OrderStatus::Picked, $order->status());
    }

    // OUT-03
    public function testCannotPickMoreThanWasAllocated(): void
    {
        $order = OutboundMother::orderInWave([5]);

        self::assertDomainError('outbound.picked_exceeds_allocated', ['line' => 1, 'picked' => 6, 'allocated' => 5], static fn () => $order->recordPick(1, 6, 0, OutboundMother::at()));
        self::assertSame(0, $order->lines()[0]->pickedUnits, 'A refused pick changes nothing.');
    }

    public function testAnOrderNotInAWaveHasNothingToPick(): void
    {
        $order = OutboundMother::order();

        self::assertDomainError('outbound.order_not_in_wave', ['reference' => 'PED-2027-0001'], static fn () => $order->recordPick(1, 1, 0, OutboundMother::at()));
    }

    public function testALineThatDoesNotExistIsRefused(): void
    {
        $order = OutboundMother::orderInWave([5]);

        self::assertDomainError('outbound.order_line_not_found', ['line' => 4], static fn () => $order->recordPick(4, 1, 0, OutboundMother::at()));
    }

    // OUT-04: the goods are off the shelves
    public function testAPickedOrderCannotBeCancelled(): void
    {
        $order = OutboundMother::orderInWave([5]);
        $order->recordPick(1, 5, 0, OutboundMother::at());

        self::assertDomainError('outbound.order_already_picked', ['reference' => 'PED-2027-0001'], static fn (): array => $order->cancel(OutboundMother::at()));
        self::assertSame(OrderStatus::Picked, $order->status());
    }

    public function testAPickedOrderCannotBeAllocatedAgain(): void
    {
        $order = OutboundMother::orderInWave([5]);
        $order->recordPick(1, 5, 0, OutboundMother::at());

        self::assertDomainError('outbound.order_already_in_wave', ['reference' => 'PED-2027-0001'], static fn () => $order->recordAllocations([new LineAllocation(1, OutboundMother::reservationId(), 5)], OutboundMother::at()));
    }
}
