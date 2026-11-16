<?php

declare(strict_types=1);

namespace App\Tests\Functional\Outbound\Api;

use App\Identity\Domain\Role;
use App\Inventory\Domain\StockStatus;
use App\Tests\Unit\Support\Ids;

/**
 * Why an order is not fully reserved (ADR 0058): where the stock of the product is and what keeps
 * it from being promised (STK-06, STK-12, WH-06).
 */
final class OrderShortagesApiTest extends OutboundApiTestCase
{
    public function testSaysThereIsNoStockWhenTheProductIsNowhere(): void
    {
        $order = $this->registerOrder();
        $this->allocate($order);

        $shortage = $this->onlyShortageOf($order);

        self::assertSame(1, $shortage['lineNumber']);
        self::assertSame(10, $shortage['orderedUnits']);
        self::assertSame(0, $shortage['allocatedUnits']);
        self::assertSame(10, $shortage['shortageUnits']);
        self::assertSame([], $shortage['obstacles']);
    }

    public function testStockAtADockIsReportedWithTheCodeOfTheDock(): void
    {
        $this->stock($this->shirtId, $this->dockId, 40);
        $order = $this->registerOrder();
        $this->allocate($order);

        self::assertSame(
            [['reason' => 'at_dock', 'units' => 40, 'locationCodes' => ['D-01-01']]],
            $this->onlyShortageOf($order)['obstacles'],
        );
    }

    public function testTheAdviceFollowsTheStockOnceItIsPutAway(): void
    {
        $this->stock($this->shirtId, $this->dockId, 40);
        $order = $this->registerOrder();
        $this->allocate($order);
        self::assertCount(1, $this->shortagesOf($order));

        $this->stock($this->shirtId, $this->reserveId, 10);
        $allocated = $this->allocate($order);

        self::assertSame('allocated', $allocated['status']);
        self::assertSame([], $this->shortagesOf($order), 'A line that is covered is not short any more.');
    }

    public function testStockInABlockedLocationIsReported(): void
    {
        $this->stock($this->shirtId, $this->reserveId, 10);
        $this->request('POST', '/api/locations/'.$this->reserveId.'/block', ['reason' => 'Revisión de estanterías']);
        $order = $this->registerOrder();
        $this->allocate($order);

        self::assertSame(
            [['reason' => 'blocked_location', 'units' => 10, 'locationCodes' => ['A-01-01']]],
            $this->onlyShortageOf($order)['obstacles'],
        );
    }

    public function testStockOfAnotherWarehouseIsReported(): void
    {
        $this->stock($this->shirtId, $this->otherWarehouseLocationId, 25);
        $order = $this->registerOrder();
        $this->allocate($order);

        self::assertSame(
            [['reason' => 'other_warehouse', 'units' => 25, 'locationCodes' => ['A-01-01']]],
            $this->onlyShortageOf($order)['obstacles'],
        );
    }

    public function testStockInAPlaceNotMeantForPickingIsReported(): void
    {
        $this->stock($this->shirtId, $this->stagingId, 7);
        $order = $this->registerOrder();
        $this->allocate($order);

        self::assertSame(
            [['reason' => 'not_pickable_location', 'units' => 7, 'locationCodes' => ['E-01-01']]],
            $this->onlyShortageOf($order)['obstacles'],
        );
    }

    public function testStockThatIsNotAvailableIsReported(): void
    {
        $this->stock($this->shirtId, $this->reserveId, 12, status: StockStatus::Quarantine);
        $order = $this->registerOrder();
        $this->allocate($order);

        self::assertSame(
            [['reason' => 'not_available', 'units' => 12, 'locationCodes' => ['A-01-01']]],
            $this->onlyShortageOf($order)['obstacles'],
        );
    }

    // STK-12
    public function testStockWithTooLittleShelfLifeIsReported(): void
    {
        $this->stock($this->yoghurtId, $this->reserveId, 30, lot: 'L-1', expiry: new \DateTimeImmutable('+5 days')->format('Y-m-d'));
        $order = $this->registerOrder(lines: [['productId' => $this->yoghurtId, 'units' => 10]]);
        $this->allocate($order);

        self::assertSame(
            [['reason' => 'shelf_life', 'units' => 30, 'locationCodes' => ['A-01-01']]],
            $this->onlyShortageOf($order)['obstacles'],
        );
    }

    public function testUnitsPromisedToAnotherOrderAreReportedAsHeldByOthers(): void
    {
        $this->stock($this->shirtId, $this->reserveId, 10);
        $first = $this->registerOrder('PED-2027-0001');
        $second = $this->registerOrder('PED-2027-0002');
        $this->allocate($first);
        $this->allocate($second);

        self::assertSame([], $this->shortagesOf($first));
        self::assertSame(
            [['reason' => 'reserved_for_others', 'units' => 10, 'locationCodes' => []]],
            $this->onlyShortageOf($second)['obstacles'],
        );
    }

    public function testWhatTheOrderItselfHoldsIsNotHeldByOthers(): void
    {
        $this->stock($this->shirtId, $this->reserveId, 6);
        $order = $this->registerOrder();
        $this->allocate($order);

        $shortage = $this->onlyShortageOf($order);

        self::assertSame(4, $shortage['shortageUnits']);
        self::assertSame([], $shortage['obstacles'], 'The 6 units it holds are its own, and there is nothing else.');
    }

    public function testSeveralReasonsAreGivenInAFixedOrder(): void
    {
        $this->stock($this->shirtId, $this->otherWarehouseLocationId, 3);
        $this->stock($this->shirtId, $this->dockId, 40);
        $order = $this->registerOrder();
        $this->allocate($order);

        $obstacles = $this->onlyShortageOf($order)['obstacles'];
        self::assertIsArray($obstacles);
        $reasons = array_column($obstacles, 'reason');

        self::assertSame(['at_dock', 'other_warehouse'], $reasons);
    }

    public function testAnOrderOfAnotherTenantIsNotFound(): void
    {
        $order = $this->registerOrder();

        $this->assertProblem(404, 'outbound.order_not_found', $this->request('GET', '/api/orders/'.$this->id($order).'/shortages', tenant: Ids::next()));
    }

    public function testAnOperatorMayAskWhy(): void
    {
        $order = $this->registerOrder();
        $this->role = Role::Operator;

        $this->request('GET', '/api/orders/'.$this->id($order).'/shortages');

        $this->assertStatus(200);
    }

    /**
     * @param array<array-key, mixed> $order
     *
     * @return list<array<array-key, mixed>>
     */
    private function shortagesOf(array $order): array
    {
        $shortages = $this->request('GET', '/api/orders/'.$this->id($order).'/shortages');
        $this->assertStatus(200);

        return array_values(array_filter($shortages, is_array(...)));
    }

    /**
     * @param array<array-key, mixed> $order
     *
     * @return array<array-key, mixed>
     */
    private function onlyShortageOf(array $order): array
    {
        $shortages = $this->shortagesOf($order);
        self::assertCount(1, $shortages);

        return $shortages[0];
    }
}
