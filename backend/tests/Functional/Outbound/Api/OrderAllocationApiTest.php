<?php

declare(strict_types=1);

namespace App\Tests\Functional\Outbound\Api;

use App\Inventory\Domain\ReservationId;
use App\Inventory\Domain\StockItem;
use App\Inventory\Domain\StockItemId;
use App\Inventory\Domain\StockReservation;
use App\Inventory\Domain\StockStatus;
use App\Tests\Unit\Support\Ids;

/**
 * Promising stock to an order (STK-02, STK-06, STK-11..14, OUT-03, OUT-04).
 */
final class OrderAllocationApiTest extends OutboundApiTestCase
{
    public function testReservesTheStockAndMarksTheOrderAllocated(): void
    {
        $stockItem = $this->stock($this->shirtId, $this->reserveId, 25);
        $order = $this->registerOrder(lines: [['productId' => $this->shirtId, 'units' => 10]]);

        $allocated = $this->allocate($order);

        $this->assertStatus(200);
        self::assertSame('allocated', $allocated['status']);
        self::assertSame(0, $allocated['shortageUnits']);
        self::assertSame(10, $this->lineOf($allocated, 1)['allocatedUnits']);
        self::assertIsString($this->lineOf($allocated, 1)['reservationId']);
        self::assertSame(10, $this->reservedIn($stockItem), 'The units are promised, and they have not moved.');
        $this->assertOnHand($stockItem, 25);
    }

    // STK-11: a shortage is not an error
    public function testCoversWhatItCanAndReportsTheShortage(): void
    {
        $stockItem = $this->stock($this->shirtId, $this->reserveId, 6);
        $order = $this->registerOrder(lines: [['productId' => $this->shirtId, 'units' => 10]]);

        $allocated = $this->allocate($order);

        $this->assertStatus(200);
        self::assertSame('partially_allocated', $allocated['status']);
        self::assertSame(6, $this->lineOf($allocated, 1)['allocatedUnits']);
        self::assertSame(4, $this->lineOf($allocated, 1)['shortageUnits']);
        self::assertSame(4, $allocated['shortageUnits']);
        self::assertSame(6, $this->reservedIn($stockItem));
    }

    public function testAnOrderWithNoStockStaysRegistered(): void
    {
        $allocated = $this->allocate($this->registerOrder());

        $this->assertStatus(200);
        self::assertSame('registered', $allocated['status']);
        self::assertSame(10, $allocated['shortageUnits']);
    }

    // The reference of the reservation is the order line, so allocating again tops it up.
    public function testAllocatingAgainCoversTheRestWhenStockArrives(): void
    {
        $first = $this->stock($this->shirtId, $this->reserveId, 6);
        $order = $this->registerOrder();
        $reservationId = $this->lineOf($this->allocate($order), 1)['reservationId'];
        $second = $this->stock($this->shirtId, $this->secondReserveId, 20, received: '2026-10-01');

        $allocated = $this->allocate($order);

        self::assertSame('allocated', $allocated['status']);
        self::assertSame($reservationId, $this->lineOf($allocated, 1)['reservationId'], 'The same reservation grew. There is no second one.');
        self::assertSame(6, $this->reservedIn($first));
        self::assertSame(4, $this->reservedIn($second));
        self::assertSame(1, $this->countReservations());
    }

    // STK-14 in spirit: repeating the call changes nothing
    public function testAllocatingAnOrderThatIsCoveredChangesNothing(): void
    {
        $stockItem = $this->stock($this->shirtId, $this->reserveId, 25);
        $order = $this->registerOrder();
        $this->allocate($order);

        $again = $this->allocate($order);

        $this->assertStatus(200);
        self::assertSame('allocated', $again['status']);
        self::assertSame(10, $this->reservedIn($stockItem), 'The units are not promised twice.');
        self::assertSame(1, $this->countReservations());
    }

    // STK-02: stock promised to one order is not available to another
    public function testTwoOrdersDoNotShareTheSameUnits(): void
    {
        $stockItem = $this->stock($this->shirtId, $this->reserveId, 15);
        $first = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 10]]));
        $second = $this->allocate($this->registerOrder('PED-2', [['productId' => $this->shirtId, 'units' => 10]]));

        self::assertSame('allocated', $first['status']);
        self::assertSame('partially_allocated', $second['status']);
        self::assertSame(5, $this->lineOf($second, 1)['allocatedUnits']);
        self::assertSame(15, $this->reservedIn($stockItem), 'Never more than the stock holds.');
    }

    // STK-12: FIFO for products with no expiry date
    public function testTakesTheOldestArrivalFirstForAProductWithoutExpiry(): void
    {
        $newer = $this->stock($this->shirtId, $this->reserveId, 10, received: '2026-10-10');
        $older = $this->stock($this->shirtId, $this->secondReserveId, 10, received: '2026-09-01');

        $this->allocate($this->registerOrder(lines: [['productId' => $this->shirtId, 'units' => 12]]));

        self::assertSame(10, $this->reservedIn($older));
        self::assertSame(2, $this->reservedIn($newer));
    }

    // STK-12: FEFO for products tracked by expiry date
    public function testTakesTheStockThatExpiresFirstForAProductTrackedByExpiry(): void
    {
        $arrivedEarlierExpiresLater = $this->stock($this->yoghurtId, $this->reserveId, 10, 'L-A', '2027-09-30', received: '2026-08-01');
        $arrivedLaterExpiresSooner = $this->stock($this->yoghurtId, $this->secondReserveId, 10, 'L-B', '2027-03-31', received: '2026-10-01');

        $this->allocate($this->registerOrder(lines: [['productId' => $this->yoghurtId, 'units' => 12]]));

        self::assertSame(10, $this->reservedIn($arrivedLaterExpiresSooner));
        self::assertSame(2, $this->reservedIn($arrivedEarlierExpiresLater));
    }

    // STK-12: the product must reach the customer with 30 days left
    public function testSkipsStockThatWouldExpireBeforeTheShelfLifeTheProductPromises(): void
    {
        $tooSoon = $this->stock($this->yoghurtId, $this->reserveId, 10, 'L-A', new \DateTimeImmutable('+10 days')->format('Y-m-d'));
        $fine = $this->stock($this->yoghurtId, $this->secondReserveId, 10, 'L-B', new \DateTimeImmutable('+200 days')->format('Y-m-d'));

        $allocated = $this->allocate($this->registerOrder(lines: [['productId' => $this->yoghurtId, 'units' => 15]]));

        self::assertSame(0, $this->reservedIn($tooSoon));
        self::assertSame(10, $this->reservedIn($fine));
        self::assertSame(10, $this->lineOf($allocated, 1)['allocatedUnits'], 'The short-dated units are a shortage, not a compromise.');
    }

    // STK-06
    public function testOnlyAvailableStockCanBeReserved(): void
    {
        $quarantine = $this->stock($this->shirtId, $this->reserveId, 10, status: StockStatus::Quarantine);
        $damaged = $this->stock($this->shirtId, $this->secondReserveId, 10, status: StockStatus::Damaged);

        $allocated = $this->allocate($this->registerOrder());

        self::assertSame('registered', $allocated['status']);
        self::assertSame(0, $this->reservedIn($quarantine));
        self::assertSame(0, $this->reservedIn($damaged));
    }

    // STK-06, WH-06
    public function testStockInABlockedLocationIsNotReserved(): void
    {
        $blocked = $this->stock($this->shirtId, $this->reserveId, 10);
        $good = $this->stock($this->shirtId, $this->secondReserveId, 10, received: '2026-10-01');
        $this->request('POST', '/api/locations/'.$this->reserveId.'/block', ['reason' => 'Revisión de estanterías']);
        $this->assertStatus(200);

        $this->allocate($this->registerOrder());

        self::assertSame(0, $this->reservedIn($blocked), 'It is older, but its location is blocked.');
        self::assertSame(10, $this->reservedIn($good));
    }

    // Stock at a dock has not been put away yet
    public function testStockAtADockIsNotReserved(): void
    {
        $atTheDock = $this->stock($this->shirtId, $this->dockId, 10);

        $allocated = $this->allocate($this->registerOrder());

        self::assertSame('registered', $allocated['status']);
        self::assertSame(0, $this->reservedIn($atTheDock));
    }

    public function testStockInTheOrderOfPickFacesAndReservesAlikeIsUsed(): void
    {
        $pickFace = $this->stock($this->shirtId, $this->pickFaceId, 4, received: '2026-09-01');
        $reserve = $this->stock($this->shirtId, $this->reserveId, 20, received: '2026-09-02');

        $this->allocate($this->registerOrder());

        self::assertSame(4, $this->reservedIn($pickFace));
        self::assertSame(6, $this->reservedIn($reserve));
    }

    public function testOnlyTheStockOfTheWarehouseTheOrderIsServedFromIsUsed(): void
    {
        $elsewhere = $this->stock($this->shirtId, $this->otherWarehouseLocationId, 50);

        $allocated = $this->allocate($this->registerOrder());

        self::assertSame('registered', $allocated['status']);
        self::assertSame(0, $this->reservedIn($elsewhere));
    }

    public function testOnlyTheStockOfTheProductOrderedIsUsed(): void
    {
        $other = $this->stock($this->yoghurtId, $this->reserveId, 50, 'L-A', '2028-01-31');

        $this->allocate($this->registerOrder());

        self::assertSame(0, $this->reservedIn($other));
    }

    public function testTheStockOfAnotherTenantIsNotUsed(): void
    {
        $foreign = $this->stockOfAnotherTenant();

        $allocated = $this->allocate($this->registerOrder());

        self::assertSame('registered', $allocated['status']);
        self::assertSame(0, $this->reservedIn($foreign));
    }

    public function testAllocatesEveryLineOfTheOrder(): void
    {
        $shirts = $this->stock($this->shirtId, $this->reserveId, 3);
        $yoghurts = $this->stock($this->yoghurtId, $this->secondReserveId, 20, 'L-A', '2028-01-31');

        $allocated = $this->allocate($this->registerOrder(lines: [
            ['productId' => $this->shirtId, 'units' => 5],
            ['productId' => $this->yoghurtId, 'units' => 20],
        ]));

        self::assertSame('partially_allocated', $allocated['status']);
        self::assertSame(3, $this->lineOf($allocated, 1)['allocatedUnits']);
        self::assertSame(20, $this->lineOf($allocated, 2)['allocatedUnits']);
        self::assertNotSame($this->lineOf($allocated, 1)['reservationId'], $this->lineOf($allocated, 2)['reservationId']);
        self::assertSame(3, $this->reservedIn($shirts));
        self::assertSame(20, $this->reservedIn($yoghurts));
    }

    // OUT-04
    public function testCancellingTheOrderGivesTheStockBack(): void
    {
        $stockItem = $this->stock($this->shirtId, $this->reserveId, 25);
        $order = $this->allocate($this->registerOrder());
        self::assertSame(10, $this->reservedIn($stockItem));

        $cancelled = $this->request('POST', '/api/orders/'.$this->id($order).'/cancel');

        $this->assertStatus(200);
        self::assertSame('cancelled', $cancelled['status']);
        self::assertSame(0, $this->reservedIn($stockItem));
        $this->assertOnHand($stockItem, 25);
        self::assertSame('released', $this->reservationStatus($this->lineOf($order, 1)['reservationId']));
    }

    public function testStockThatWasFreedCanBePromisedToAnotherOrder(): void
    {
        $this->stock($this->shirtId, $this->reserveId, 10);
        $first = $this->allocate($this->registerOrder('PED-1'));
        $this->request('POST', '/api/orders/'.$this->id($first).'/cancel');

        $second = $this->allocate($this->registerOrder('PED-2'));

        self::assertSame('allocated', $second['status']);
    }

    public function testCancellingAnOrderWithNothingReservedIsFine(): void
    {
        $cancelled = $this->request('POST', '/api/orders/'.$this->id($this->registerOrder()).'/cancel');

        $this->assertStatus(200);
        self::assertSame('cancelled', $cancelled['status']);
    }

    // OUT-04
    public function testACancelledOrderCannotBeCancelledOrAllocatedAgain(): void
    {
        $stockItem = $this->stock($this->shirtId, $this->reserveId, 25);
        $order = $this->registerOrder();
        $this->request('POST', '/api/orders/'.$this->id($order).'/cancel');

        $this->assertProblem(409, 'outbound.order_already_cancelled', $this->request('POST', '/api/orders/'.$this->id($order).'/cancel'));
        $this->assertProblem(409, 'outbound.order_already_cancelled', $this->allocate($order));
        self::assertSame(0, $this->reservedIn($stockItem), 'A cancelled order reserves nothing.');
    }

    public function testAnOrderOfAnotherTenantCannotBeAllocatedOrCancelled(): void
    {
        $order = $this->registerOrder();

        $this->assertProblem(404, 'outbound.order_not_found', $this->request('POST', '/api/orders/'.$this->id($order).'/allocate', tenant: Ids::next()));
        $this->assertProblem(404, 'outbound.order_not_found', $this->request('POST', '/api/orders/'.$this->id($order).'/cancel', tenant: Ids::next()));
    }

    public function testReportsAnOrderThatDoesNotExist(): void
    {
        $this->assertProblem(404, 'outbound.order_not_found', $this->request('POST', '/api/orders/'.Ids::next().'/allocate'));
        $this->assertProblem(400, 'shared.identifier_invalid', $this->request('POST', '/api/orders/not-an-id/allocate'));
    }

    private function assertOnHand(string $stockItemId, int $expected): void
    {
        $entityManager = $this->entityManager();
        $this->seeEveryTenant($entityManager);
        $stockItem = $entityManager->find(StockItem::class, StockItemId::fromString($stockItemId));
        self::assertInstanceOf(StockItem::class, $stockItem);
        self::assertSame($expected, $stockItem->onHand()->units);
    }

    private function countReservations(): int
    {
        $count = $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM stock_reservations');
        \assert(is_numeric($count));

        return (int) $count;
    }

    private function reservationStatus(mixed $reservationId): string
    {
        self::assertIsString($reservationId);
        $entityManager = $this->entityManager();
        $this->seeEveryTenant($entityManager);
        $reservation = $entityManager->find(StockReservation::class, ReservationId::fromString($reservationId));
        self::assertInstanceOf(StockReservation::class, $reservation);

        return $reservation->status()->value;
    }

    /**
     * Stock of the product that belongs to another tenant, in a location of another tenant.
     */
    private function stockOfAnotherTenant(): string
    {
        $tenant = $this->tenant;
        $this->tenant = Ids::next();

        try {
            return $this->stock($this->shirtId, $this->reserveId, 50);
        } finally {
            $this->tenant = $tenant;
        }
    }
}
