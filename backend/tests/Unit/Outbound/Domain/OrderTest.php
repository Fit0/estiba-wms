<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\Event\OrderAllocated;
use App\Outbound\Domain\Event\OrderCancelled;
use App\Outbound\Domain\Event\OrderRegistered;
use App\Outbound\Domain\LineAllocation;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderedItem;
use App\Outbound\Domain\OrderStatus;
use App\Outbound\Domain\ReservationId;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    use AssertsDomainErrors;

    public function testRegistersAnOrderWithItsLinesNumberedFromOne(): void
    {
        $order = OutboundMother::order([OutboundMother::item(10), OutboundMother::item(4)]);

        self::assertSame(OrderStatus::Registered, $order->status());
        self::assertSame([1, 2], array_map(static fn ($line): int => $line->lineNumber, $order->lines()));
        self::assertSame([10, 4], array_map(static fn ($line): int => $line->orderedUnits, $order->lines()));
        self::assertSame([0, 0], array_map(static fn ($line): int => $line->allocatedUnits, $order->lines()));
        self::assertSame(14, $order->shortageUnits(), 'Nothing is covered yet.');
        self::assertSame('PED-2027-0001', $order->reference()->value);
    }

    public function testRegisteringRaisesAnEvent(): void
    {
        $order = OutboundMother::order();

        $events = $order->pullEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(OrderRegistered::class, $events[0]);
        self::assertSame('PED-2027-0001', $events[0]->reference->value);
        self::assertEquals(OutboundMother::at(), $events[0]->occurredAt());
    }

    // OUT-01
    public function testTheReferenceIsUniquePerTenant(): void
    {
        $tenantId = OutboundMother::tenantId();
        $registry = new InMemoryOrderReferences()->take($tenantId, 'PED-2027-0001');

        self::assertDomainError('outbound.order_reference_already_used', ['reference' => 'PED-2027-0001'], static fn (): Order => OutboundMother::order(tenantId: $tenantId, registry: $registry));

        $other = OutboundMother::order(tenantId: OutboundMother::tenantId(), registry: $registry);
        self::assertSame('PED-2027-0001', $other->reference()->value, 'Another tenant may use the same reference.');
    }

    // OUT-02
    public function testAnOrderNeedsAtLeastOneLine(): void
    {
        self::assertDomainError('outbound.order_lines_invalid', ['reason' => 'empty', 'maxLines' => 500], static fn (): Order => OutboundMother::order([]));
    }

    // OUT-02
    public function testAnOrderHoldsAtMostFiveHundredLines(): void
    {
        $items = array_map(static fn (int $i): OrderedItem => OutboundMother::item(1), range(1, 501));

        self::assertDomainError('outbound.order_lines_invalid', ['reason' => 'too_many', 'maxLines' => 500], static fn (): Order => OutboundMother::order($items));
        self::assertCount(500, OutboundMother::order(\array_slice($items, 0, 500))->lines());
    }

    // OUT-02
    public function testEveryLineAsksForAtLeastOneUnit(): void
    {
        self::assertDomainError('outbound.ordered_quantity_invalid', ['line' => 2, 'units' => 0], static fn (): Order => OutboundMother::order([OutboundMother::item(5), OutboundMother::item(0)]));
        self::assertDomainError('outbound.ordered_quantity_invalid', ['line' => 1, 'units' => -3], static fn (): Order => OutboundMother::order([OutboundMother::item(-3)]));
    }

    // OUT-02
    public function testOnlyActiveProductsCanBeOrdered(): void
    {
        $blocked = OutboundMother::item(5, available: false);

        self::assertDomainError('outbound.product_not_orderable', ['productId' => $blocked->product->id->value, 'reason' => 'unavailable'], static fn (): Order => OutboundMother::order([OutboundMother::item(5), $blocked]));
    }

    // OUT-03
    public function testRecordingAllocationsCoversTheLinesAndTheOrderBecomesPartlyAllocated(): void
    {
        $order = OutboundMother::order([OutboundMother::item(10), OutboundMother::item(4)]);
        $reservation = OutboundMother::reservationId();

        $order->recordAllocations([new LineAllocation(1, $reservation, 6)], OutboundMother::at());

        self::assertSame(OrderStatus::PartiallyAllocated, $order->status());
        self::assertSame(6, $order->lines()[0]->allocatedUnits);
        self::assertSame(4, $order->lines()[0]->shortageUnits());
        self::assertSame($reservation->value, $order->lines()[0]->reservationId?->value);
        self::assertNull($order->lines()[1]->reservationId);
        self::assertSame(8, $order->shortageUnits());
    }

    public function testTheOrderIsAllocatedWhenEveryLineIsFullyCovered(): void
    {
        $order = OutboundMother::order([OutboundMother::item(10), OutboundMother::item(4)]);
        $order->pullEvents();

        $order->recordAllocations([
            new LineAllocation(1, OutboundMother::reservationId(), 10),
            new LineAllocation(2, OutboundMother::reservationId(), 4),
        ], OutboundMother::at());

        self::assertSame(OrderStatus::Allocated, $order->status());
        self::assertSame(0, $order->shortageUnits());
        $events = $order->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderAllocated::class, $events[0]);
    }

    public function testAnAllocationOfNothingLeavesTheOrderRegistered(): void
    {
        $order = OutboundMother::order();

        $order->recordAllocations([new LineAllocation(1, OutboundMother::reservationId(), 0)], OutboundMother::at());

        self::assertSame(OrderStatus::Registered, $order->status(), 'A shortage of everything is not an allocation.');
    }

    public function testAllocatingAgainTopsUpAndAnnouncesTheOrderOnce(): void
    {
        $order = OutboundMother::order([OutboundMother::item(10)]);
        $reservation = OutboundMother::reservationId();
        $order->recordAllocations([new LineAllocation(1, $reservation, 4)], OutboundMother::at());
        $order->pullEvents();

        $order->recordAllocations([new LineAllocation(1, $reservation, 10)], OutboundMother::at());
        self::assertSame(OrderStatus::Allocated, $order->status());
        self::assertCount(1, $order->pullEvents());

        $order->recordAllocations([new LineAllocation(1, $reservation, 10)], OutboundMother::at());
        self::assertSame([], $order->pullEvents(), 'Repeating an allocation that changes nothing announces nothing.');
    }

    // OUT-03
    public function testNeverAllocatesMoreThanWasOrdered(): void
    {
        $order = OutboundMother::order([OutboundMother::item(10), OutboundMother::item(4)]);

        self::assertDomainError(
            'outbound.allocation_exceeds_ordered',
            ['line' => 2, 'allocated' => 5, 'ordered' => 4],
            static fn () => $order->recordAllocations([new LineAllocation(1, OutboundMother::reservationId(), 3), new LineAllocation(2, OutboundMother::reservationId(), 5)], OutboundMother::at()),
        );

        self::assertSame(OrderStatus::Registered, $order->status());
        self::assertSame(0, $order->lines()[0]->allocatedUnits, 'A refused allocation changes no line.');
    }

    public function testAnAllocationToALineThatDoesNotExistIsRefused(): void
    {
        $order = OutboundMother::order([OutboundMother::item(10)]);

        self::assertDomainError('outbound.order_line_not_found', ['line' => 3], static fn () => $order->recordAllocations([new LineAllocation(3, OutboundMother::reservationId(), 1)], OutboundMother::at()));
    }

    // OUT-04
    public function testCancellingReturnsTheReservationsToRelease(): void
    {
        $order = OutboundMother::order([OutboundMother::item(10), OutboundMother::item(4), OutboundMother::item(2)]);
        $first = OutboundMother::reservationId();
        $second = OutboundMother::reservationId();
        $order->recordAllocations([new LineAllocation(1, $first, 10), new LineAllocation(2, $second, 1)], OutboundMother::at());
        $order->pullEvents();

        $reservations = $order->cancel(OutboundMother::at());

        self::assertSame(OrderStatus::Cancelled, $order->status());
        self::assertSame([$first->value, $second->value], array_map(static fn (ReservationId $id): string => $id->value, $reservations), 'The line without stock holds no reservation.');
        $events = $order->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderCancelled::class, $events[0]);
    }

    public function testAnOrderWithNothingReservedCancelsWithNothingToRelease(): void
    {
        self::assertSame([], OutboundMother::order()->cancel(OutboundMother::at()));
    }

    // OUT-04
    public function testACancelledOrderIsFinished(): void
    {
        $order = OutboundMother::order();
        $order->cancel(OutboundMother::at());

        self::assertDomainError('outbound.order_already_cancelled', ['reference' => 'PED-2027-0001'], static fn (): array => $order->cancel(OutboundMother::at()));
        self::assertDomainError('outbound.order_already_cancelled', ['reference' => 'PED-2027-0001'], static fn () => $order->recordAllocations([new LineAllocation(1, OutboundMother::reservationId(), 1)], OutboundMother::at()));
        self::assertDomainError('outbound.order_already_cancelled', ['reference' => 'PED-2027-0001'], static fn () => $order->assertNotCancelled());
    }
}
