<?php

declare(strict_types=1);

namespace App\Tests\Functional\Outbound\Api;

use App\Identity\Domain\Role;
use App\Tests\Unit\Support\Ids;

/**
 * Grouping allocated orders into waves, releasing them into pick lists walked in a serpentine
 * route, and calling them off (OUT-05 to OUT-09, OUT-14).
 */
final class WaveApiTest extends OutboundApiTestCase
{
    private string $a1;

    private string $a2;

    private string $b1;

    private string $b2;

    private string $c1;

    private bool $shirtsAtTheFirstLocation = false;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Three aisles, with the pick sequence that says where each location is.
        $this->a1 = $this->location($this->warehouseId, $this->zoneId, 'A-02-01', 'reserve', ['pickSequence' => 10]);
        $this->a2 = $this->location($this->warehouseId, $this->zoneId, 'A-02-02', 'reserve', ['pickSequence' => 20]);
        $this->b1 = $this->location($this->warehouseId, $this->zoneId, 'B-02-01', 'pick_face', ['pickSequence' => 30]);
        $this->b2 = $this->location($this->warehouseId, $this->zoneId, 'B-02-02', 'pick_face', ['pickSequence' => 40]);
        $this->c1 = $this->location($this->warehouseId, $this->zoneId, 'C-02-01', 'reserve', ['pickSequence' => 50]);
    }

    public function testGroupsAllocatedOrdersIntoAPlannedWave(): void
    {
        $first = $this->allocatedOrder('PED-1', 5);
        $second = $this->allocatedOrder('PED-2', 5);

        $wave = $this->createWave([$first, $second]);

        $this->assertStatus(201);
        self::assertSame('planned', $wave['status']);
        self::assertSame($this->warehouseId, $wave['warehouseId']);
        self::assertSame([$this->id($first), $this->id($second)], $wave['orderIds']);
        self::assertSame([], $wave['pickListIds']);
        self::assertNull($wave['releasedAt']);
    }

    public function testTheOrdersOfTheWaveAreMarkedAsInTheWave(): void
    {
        $order = $this->allocatedOrder('PED-1', 5);

        $wave = $this->createWave([$order]);

        $read = $this->request('GET', '/api/orders/'.$this->id($order));
        self::assertSame('in_wave', $read['status']);
        self::assertSame($this->id($wave), $read['waveId']);
        self::assertSame(5, $this->lineOf($read, 1)['allocatedUnits'], 'It keeps the stock that was promised to it.');
    }

    // OUT-06
    public function testAnOrderThatIsNotAllocatedCannotJoinAWave(): void
    {
        $order = $this->registerOrder('PED-1');

        $problem = $this->createWave([$order]);

        $this->assertProblem(422, 'outbound.order_not_waveable', $problem);
        self::assertSame('registered', $this->parameter($problem, 'status'));
    }

    // OUT-06
    public function testAPartlyAllocatedOrderJoinsOnlyOnceTheBackorderIsAccepted(): void
    {
        $this->stock($this->shirtId, $this->a1, 4);
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 10]]));
        self::assertSame('partially_allocated', $order['status']);

        $this->assertProblem(422, 'outbound.order_not_waveable', $this->createWave([$order]));

        $accepted = $this->request('POST', '/api/orders/'.$this->id($order).'/accept-backorder');
        $this->assertStatus(200);
        self::assertTrue($accepted['backorderAccepted']);
        self::assertSame('partially_allocated', $accepted['status']);

        $this->createWave([$order]);
        $this->assertStatus(201);
    }

    public function testOnlyAPartlyAllocatedOrderHasABackorderToAccept(): void
    {
        $registered = $this->registerOrder('PED-1');
        $this->assertProblem(422, 'outbound.backorder_not_applicable', $this->request('POST', '/api/orders/'.$this->id($registered).'/accept-backorder'));

        $allocated = $this->allocatedOrder('PED-2', 5);
        $this->assertProblem(422, 'outbound.backorder_not_applicable', $this->request('POST', '/api/orders/'.$this->id($allocated).'/accept-backorder'));
    }

    // OUT-05
    public function testAnOrderBelongsToAtMostOneWave(): void
    {
        $order = $this->allocatedOrder('PED-1', 5);
        $this->createWave([$order]);

        $problem = $this->createWave([$order]);

        $this->assertProblem(409, 'outbound.order_already_in_wave', $problem);
        self::assertSame('PED-1', $this->parameter($problem, 'reference'));
    }

    // OUT-14
    public function testOrdersServedFromDifferentWarehousesCannotShareAWave(): void
    {
        $here = $this->allocatedOrder('PED-1', 5);
        $this->stock($this->shirtId, $this->otherWarehouseLocationId, 5);
        $there = $this->allocate($this->registerOrder('PED-2', [['productId' => $this->shirtId, 'units' => 5]], ['warehouseId' => $this->otherWarehouseId]));
        self::assertSame('allocated', $there['status']);

        $this->assertProblem(422, 'outbound.wave_warehouse_mismatch', $this->createWave([$here, $there]));
    }

    public function testRefusesAWaveWithoutOrdersWithARepeatedOrAnUnknownOne(): void
    {
        $order = $this->allocatedOrder('PED-1', 5);

        $this->assertProblem(422, 'outbound.wave_orders_invalid', $this->request('POST', '/api/waves', ['orderIds' => []]));
        $repeated = $this->request('POST', '/api/waves', ['orderIds' => [$this->id($order), $this->id($order)]]);
        $this->assertProblem(422, 'outbound.wave_orders_invalid', $repeated);
        self::assertSame('repeated', $this->parameter($repeated, 'reason'));
        $this->assertProblem(404, 'outbound.order_not_found', $this->request('POST', '/api/waves', ['orderIds' => [Ids::next()]]));
        $this->assertProblem(400, 'shared.request_malformed', $this->request('POST', '/api/waves', ['orderIds' => [12]]));
    }

    public function testAnOrderOfAnotherTenantCannotJoinAWave(): void
    {
        $order = $this->allocatedOrder('PED-1', 5);

        $this->assertProblem(404, 'outbound.order_not_found', $this->request('POST', '/api/waves', ['orderIds' => [$this->id($order)]], Ids::next()));
    }

    // The wave and its orders change together or not at all
    public function testAWaveRefusedForOneOrderLeavesNoOrderInIt(): void
    {
        $good = $this->allocatedOrder('PED-1', 5);
        $notYet = $this->registerOrder('PED-2');

        $this->assertProblem(422, 'outbound.order_not_waveable', $this->createWave([$good, $notYet]));

        self::assertSame('allocated', $this->request('GET', '/api/orders/'.$this->id($good))['status']);
        self::assertSame([], $this->request('GET', '/api/waves'));
    }

    // OUT-09
    public function testReleasingBuildsOnePickListWalkedInSerpentineOrder(): void
    {
        $this->stockEveryLocationWithTwoShirts();
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 10]]));
        self::assertSame('allocated', $order['status']);
        $wave = $this->createWave([$order]);

        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release');

        $this->assertStatus(200);
        self::assertSame('released', $released['status']);
        self::assertNotNull($released['releasedAt']);
        self::assertCount(1, $this->listOf($released, 'pickListIds'));
        $list = $this->request('GET', '/api/pick-lists/'.$this->pickListId($released, 0));
        $this->assertStatus(200);
        self::assertSame($this->id($wave), $list['waveId']);
        self::assertSame('generated', $list['status']);
        self::assertSame(10, $list['unitsToPick']);
        self::assertSame([1, 2, 3, 4, 5], $this->column($list, 'lines', 'position'));
        // Aisle A forwards, aisle B backwards, aisle C forwards again.
        self::assertSame(['A-02-01', 'A-02-02', 'B-02-02', 'B-02-01', 'C-02-01'], $this->column($list, 'lines', 'locationCode'));
        self::assertSame([0, 0, 0, 0, 0], $this->column($list, 'lines', 'quantityPicked'));
    }

    // OUT-09 holds whatever the order the stock was received in
    public function testTheRouteDoesNotDependOnWhichStockWasReservedFirst(): void
    {
        $this->stock($this->shirtId, $this->c1, 2, received: '2026-08-01');
        $this->stock($this->shirtId, $this->b2, 2, received: '2026-08-02');
        $this->stock($this->shirtId, $this->a2, 2, received: '2026-08-03');
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 6]]));
        $wave = $this->createWave([$order]);

        $list = $this->releasedList($this->request('POST', '/api/waves/'.$this->id($wave).'/release'), 0);

        self::assertSame(['A-02-02', 'B-02-02', 'C-02-01'], $this->column($list, 'lines', 'locationCode'));
    }

    public function testSharesTheWaveAmongSeveralOperatorsAsStretchesOfTheRoute(): void
    {
        $this->stockEveryLocationWithTwoShirts();
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 10]]));
        $wave = $this->createWave([$order]);

        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release', ['pickLists' => 2]);

        self::assertCount(2, $this->listOf($released, 'pickListIds'));
        $first = $this->releasedList($released, 0);
        $second = $this->releasedList($released, 1);
        self::assertSame(['A-02-01', 'A-02-02', 'B-02-02'], $this->column($first, 'lines', 'locationCode'));
        self::assertSame(['B-02-01', 'C-02-01'], $this->column($second, 'lines', 'locationCode'));
        self::assertSame([1, 2, 3], $this->column($first, 'lines', 'position'));
        self::assertSame([1, 2], $this->column($second, 'lines', 'position'), 'Each list is numbered from its own first stop.');
        self::assertSame(10, $this->number($first, 'unitsToPick') + $this->number($second, 'unitsToPick'));
        self::assertCount(2, $this->request('GET', '/api/pick-lists', query: ['waveId' => $this->id($wave)]));
    }

    public function testNeverMakesMoreListsThanThereArePicks(): void
    {
        $this->stock($this->shirtId, $this->a1, 5);
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 5]]));
        $wave = $this->createWave([$order]);

        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release', ['pickLists' => 4]);

        self::assertCount(1, $this->listOf($released, 'pickListIds'));
    }

    // The pick lists ask for exactly the units that were reserved
    public function testThePickListsAskForWhatWasReservedForEveryOrder(): void
    {
        $this->stock($this->shirtId, $this->a1, 6, received: '2026-09-01');
        $this->stock($this->shirtId, $this->b1, 20, received: '2026-09-02');
        $first = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 9]]));
        $second = $this->allocate($this->registerOrder('PED-2', [['productId' => $this->shirtId, 'units' => 4]]));
        $wave = $this->createWave([$first, $second]);

        $list = $this->releasedList($this->request('POST', '/api/waves/'.$this->id($wave).'/release'), 0);

        self::assertSame(13, $list['unitsToPick']);
        $byOrder = [];
        foreach ($this->listOf($list, 'lines') as $line) {
            self::assertIsArray($line);
            $orderId = $line['orderId'] ?? null;
            self::assertIsString($orderId);
            $byOrder[$orderId] = ($byOrder[$orderId] ?? 0) + $this->number($line, 'quantityToPick');
        }
        self::assertSame([$this->id($first) => 9, $this->id($second) => 4], $byOrder);
    }

    public function testEachStopCarriesTheLotAndTheExpiryDateOfTheStockToTake(): void
    {
        $this->stock($this->yoghurtId, $this->a1, 8, 'L-77', '2028-03-31');
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->yoghurtId, 'units' => 8]]));
        $wave = $this->createWave([$order]);

        $line = $this->listOf($this->releasedList($this->request('POST', '/api/waves/'.$this->id($wave).'/release'), 0), 'lines')[0];
        self::assertIsArray($line);

        self::assertSame($this->yoghurtId, $line['productId']);
        self::assertSame($this->id($order), $line['orderId']);
        self::assertSame(1, $line['orderLineNumber']);
        self::assertSame($this->a1, $line['locationId']);
        self::assertSame('L-77', $line['lot']);
        self::assertSame('2028-03-31', $line['expiryDate']);
        self::assertSame(8, $line['quantityToPick']);
    }

    public function testAPartlyCoveredOrderIsPickedOnlyForWhatIsCovered(): void
    {
        $this->stock($this->shirtId, $this->a1, 4);
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 10]]));
        $this->request('POST', '/api/orders/'.$this->id($order).'/accept-backorder');
        $wave = $this->createWave([$order]);

        $list = $this->releasedList($this->request('POST', '/api/waves/'.$this->id($wave).'/release'), 0);

        self::assertSame(4, $list['unitsToPick']);
    }

    public function testRefusesAnInvalidNumberOfPickLists(): void
    {
        $this->stock($this->shirtId, $this->a1, 5);
        $wave = $this->createWave([$this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 5]]))]);
        $uri = '/api/waves/'.$this->id($wave).'/release';

        $problem = $this->request('POST', $uri, ['pickLists' => 0]);
        $this->assertProblem(422, 'outbound.pick_list_count_invalid', $problem);
        self::assertSame(0, $this->parameter($problem, 'count'));
        $this->assertProblem(422, 'outbound.pick_list_count_invalid', $this->request('POST', $uri, ['pickLists' => 51]));
        $this->assertProblem(400, 'shared.request_malformed', $this->request('POST', $uri, ['pickLists' => 'two']));

        self::assertSame('planned', $this->request('GET', '/api/waves/'.$this->id($wave))['status'], 'A refused release leaves the wave planned.');
        self::assertSame([], $this->request('GET', '/api/pick-lists'));
    }

    // OUT-07
    public function testAWaveCannotBeReleasedTwice(): void
    {
        $this->stock($this->shirtId, $this->a1, 5);
        $wave = $this->createWave([$this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 5]]))]);
        $this->request('POST', '/api/waves/'.$this->id($wave).'/release');

        $this->assertProblem(409, 'outbound.wave_already_released', $this->request('POST', '/api/waves/'.$this->id($wave).'/release'));
        self::assertCount(1, $this->request('GET', '/api/pick-lists'), 'The second release made no second list.');
    }

    public function testCancellingAPlannedWaveFreesItsOrders(): void
    {
        $order = $this->allocatedOrder('PED-1', 5);
        $wave = $this->createWave([$order]);

        $cancelled = $this->request('POST', '/api/waves/'.$this->id($wave).'/cancel');

        $this->assertStatus(200);
        self::assertSame('cancelled', $cancelled['status']);
        $read = $this->request('GET', '/api/orders/'.$this->id($order));
        self::assertSame('allocated', $read['status']);
        self::assertNull($read['waveId']);
        $this->createWave([$order]);
        $this->assertStatus(201);
    }

    public function testCancellingAReleasedWaveCancelsItsPickListsAndKeepsTheStockReserved(): void
    {
        $stockItem = $this->stock($this->shirtId, $this->a1, 5);
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 5]]));
        $wave = $this->createWave([$order]);
        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release');

        $this->request('POST', '/api/waves/'.$this->id($wave).'/cancel');

        self::assertSame('cancelled', $this->request('GET', '/api/pick-lists/'.$this->pickListId($released, 0))['status']);
        self::assertSame('allocated', $this->request('GET', '/api/orders/'.$this->id($order))['status']);
        self::assertSame(5, $this->reservedIn($stockItem), 'The order is still allocated, so its stock is still promised.');
    }

    public function testACancelledWaveIsFinished(): void
    {
        $wave = $this->createWave([$this->allocatedOrder('PED-1', 5)]);
        $this->request('POST', '/api/waves/'.$this->id($wave).'/cancel');

        $this->assertProblem(409, 'outbound.wave_already_cancelled', $this->request('POST', '/api/waves/'.$this->id($wave).'/cancel'));
        $this->assertProblem(409, 'outbound.wave_already_cancelled', $this->request('POST', '/api/waves/'.$this->id($wave).'/release'));
    }

    public function testCancellingAnOrderOfAPlannedWaveTakesItOutOfTheWave(): void
    {
        $first = $this->allocatedOrder('PED-1', 5);
        $second = $this->allocatedOrder('PED-2', 5);
        $wave = $this->createWave([$first, $second]);

        $this->request('POST', '/api/orders/'.$this->id($first).'/cancel');
        $this->assertStatus(200);

        self::assertSame([$this->id($second)], $this->request('GET', '/api/waves/'.$this->id($wave))['orderIds']);
    }

    // OUT-07
    public function testAnOrderOfAReleasedWaveCannotBeCancelled(): void
    {
        $stockItem = $this->stock($this->shirtId, $this->a1, 5);
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 5]]));
        $wave = $this->createWave([$order]);
        $this->request('POST', '/api/waves/'.$this->id($wave).'/release');

        $problem = $this->request('POST', '/api/orders/'.$this->id($order).'/cancel');

        $this->assertProblem(409, 'outbound.order_already_in_released_wave', $problem);
        self::assertSame('in_wave', $this->request('GET', '/api/orders/'.$this->id($order))['status']);
        self::assertSame(5, $this->reservedIn($stockItem), 'Its units are on a pick list, so they stay reserved.');
    }

    public function testAnOrderInAWaveCannotBeAllocatedAgain(): void
    {
        $order = $this->allocatedOrder('PED-1', 5);
        $this->createWave([$order]);

        $this->assertProblem(409, 'outbound.order_already_in_wave', $this->allocate($order));
    }

    public function testListsWavesByStatus(): void
    {
        $planned = $this->createWave([$this->allocatedOrder('PED-1', 5)]);
        $cancelled = $this->createWave([$this->allocatedOrder('PED-2', 5)]);
        $this->request('POST', '/api/waves/'.$this->id($cancelled).'/cancel');

        self::assertCount(2, $this->request('GET', '/api/waves'));
        self::assertSame([$this->id($planned)], array_column($this->request('GET', '/api/waves', query: ['status' => 'planned']), 'id'));
        self::assertSame([$this->id($cancelled)], array_column($this->request('GET', '/api/waves', query: ['status' => 'cancelled']), 'id'));
        $this->assertProblem(400, 'shared.request_malformed', $this->request('GET', '/api/waves', query: ['status' => 'finished']));
    }

    public function testAWaveAndItsPickListsAreHiddenFromAnotherTenant(): void
    {
        $this->stock($this->shirtId, $this->a1, 5);
        $wave = $this->createWave([$this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 5]]))]);
        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release');
        $stranger = Ids::next();

        $this->assertProblem(404, 'outbound.wave_not_found', $this->request('GET', '/api/waves/'.$this->id($wave), tenant: $stranger));
        $this->assertProblem(404, 'outbound.wave_not_found', $this->request('POST', '/api/waves/'.$this->id($wave).'/cancel', tenant: $stranger));
        $this->assertProblem(404, 'outbound.pick_list_not_found', $this->request('GET', '/api/pick-lists/'.$this->pickListId($released, 0), tenant: $stranger));
        self::assertSame([], $this->request('GET', '/api/pick-lists', tenant: $stranger));
        self::assertSame([], $this->request('GET', '/api/waves', tenant: $stranger));
    }

    public function testReportsAWaveOrPickListThatDoesNotExist(): void
    {
        $this->assertProblem(404, 'outbound.wave_not_found', $this->request('GET', '/api/waves/'.Ids::next()));
        $this->assertProblem(404, 'outbound.pick_list_not_found', $this->request('GET', '/api/pick-lists/'.Ids::next()));
        $this->assertProblem(400, 'shared.identifier_invalid', $this->request('GET', '/api/waves/not-an-id'));
    }

    public function testAnOperatorCanReadWavesAndPickListsButNotChangeThem(): void
    {
        $this->stock($this->shirtId, $this->a1, 5);
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 5]]));
        $wave = $this->createWave([$order]);
        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release');
        $this->role = Role::Operator;

        $this->request('GET', '/api/waves');
        $this->assertStatus(200);
        $this->request('GET', '/api/pick-lists/'.$this->pickListId($released, 0));
        $this->assertStatus(200);

        $this->assertProblem(403, 'shared.forbidden', $this->createWave([$order]));
        $this->assertProblem(403, 'shared.forbidden', $this->request('POST', '/api/waves/'.$this->id($wave).'/release'));
        $this->assertProblem(403, 'shared.forbidden', $this->request('POST', '/api/waves/'.$this->id($wave).'/cancel'));
        $this->assertProblem(403, 'shared.forbidden', $this->request('POST', '/api/orders/'.$this->id($order).'/accept-backorder'));
    }

    public function testTheWavesAreInTheOpenApiDocument(): void
    {
        $paths = $this->openApiPaths();

        foreach (['/api/waves', '/api/waves/{id}', '/api/waves/{id}/release', '/api/waves/{id}/cancel', '/api/pick-lists', '/api/pick-lists/{id}', '/api/orders/{id}/accept-backorder'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
    }

    public function testExplainsTheErrorsInTheLanguageAsked(): void
    {
        $wave = $this->createWave([$this->allocatedOrder('PED-1', 5)]);
        $this->request('POST', '/api/waves/'.$this->id($wave).'/cancel');

        $this->acceptLanguage = 'en';
        self::assertSame(\sprintf('Wave %s is already cancelled.', $this->id($wave)), $this->text($this->request('POST', '/api/waves/'.$this->id($wave).'/cancel'), 'detail'));

        $this->acceptLanguage = null;
        self::assertSame(\sprintf('La ola %s ya está cancelada.', $this->id($wave)), $this->text($this->request('POST', '/api/waves/'.$this->id($wave).'/cancel'), 'detail'));
    }

    /**
     * An order of shirts that is fully allocated, from stock the test puts in the first location.
     *
     * @return array<array-key, mixed>
     */
    private function allocatedOrder(string $reference, int $units): array
    {
        // One stock item per location and product, so the first order puts the stock there and the others share it.
        if (!$this->shirtsAtTheFirstLocation) {
            $this->stock($this->shirtId, $this->a1, 100);
            $this->shirtsAtTheFirstLocation = true;
        }

        $order = $this->allocate($this->registerOrder($reference, [['productId' => $this->shirtId, 'units' => $units]]));
        self::assertSame('allocated', $order['status']);

        return $order;
    }

    private function stockEveryLocationWithTwoShirts(): void
    {
        foreach ([$this->a1, $this->a2, $this->b1, $this->b2, $this->c1] as $position => $location) {
            $this->stock($this->shirtId, $location, 2, received: '2026-09-0'.($position + 1));
        }
    }

    /**
     * @param list<array<array-key, mixed>> $orders
     *
     * @return array<array-key, mixed>
     */
    private function createWave(array $orders): array
    {
        return $this->request('POST', '/api/waves', ['orderIds' => array_map($this->id(...), $orders)]);
    }

    /**
     * A list inside a resource, such as the `lines` of a pick list.
     *
     * @param array<array-key, mixed> $resource
     *
     * @return list<mixed>
     */
    private function listOf(array $resource, string $field): array
    {
        $list = $resource[$field] ?? null;
        self::assertIsArray($list, \sprintf('The resource has no list "%s".', $field));

        return array_values($list);
    }

    /**
     * One field of every element of a list inside a resource.
     *
     * @param array<array-key, mixed> $resource
     *
     * @return list<mixed>
     */
    private function column(array $resource, string $field, string $key): array
    {
        return array_column($this->listOf($resource, $field), $key);
    }

    /**
     * @param array<array-key, mixed> $resource
     */
    private function number(array $resource, string $field): int
    {
        $number = $resource[$field] ?? null;
        self::assertIsInt($number, \sprintf('The resource has no number "%s".', $field));

        return $number;
    }

    /**
     * @param array<array-key, mixed> $wave a released wave
     */
    private function pickListId(array $wave, int $index): string
    {
        $id = $this->listOf($wave, 'pickListIds')[$index] ?? null;
        self::assertIsString($id);

        return $id;
    }

    /**
     * The pick list at a position of a released wave, read from the API.
     *
     * @param array<array-key, mixed> $released
     *
     * @return array<array-key, mixed>
     */
    private function releasedList(array $released, int $index): array
    {
        $this->assertStatus(200);
        $ids = $released['pickListIds'] ?? null;
        self::assertIsArray($ids);
        $id = $ids[$index] ?? null;
        self::assertIsString($id);

        return $this->request('GET', '/api/pick-lists/'.$id);
    }
}
