<?php

declare(strict_types=1);

namespace App\Tests\Functional\Outbound\Api;

use App\Identity\Domain\Role;
use App\Tests\Unit\Support\Ids;

/**
 * Walking a pick list: assigning it, starting it and confirming each stop with a scan (OUT-10 to
 * OUT-13), what that does to the stock, the order and the wave (STK-13, OUT-03, OUT-08).
 */
final class PickingApiTest extends OutboundApiTestCase
{
    private string $first;

    private string $second;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Two reserve locations in one aisle, in the order they are walked.
        $this->first = $this->location($this->warehouseId, $this->zoneId, 'A-03-01', 'reserve', ['pickSequence' => 10]);
        $this->second = $this->location($this->warehouseId, $this->zoneId, 'A-03-02', 'reserve', ['pickSequence' => 20]);
    }

    public function testWalksAListFromStartToFinish(): void
    {
        [$list, $order, $wave] = $this->listToWalk();

        $started = $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');
        $this->assertStatus(200);
        self::assertSame('in_progress', $started['status']);
        self::assertSame($this->me(), $started['operatorId']);
        self::assertNotNull($started['startedAt']);

        $afterFirst = $this->confirm($list, 1, 'A-03-01', 6);
        $this->assertStatus(200);
        self::assertSame('in_progress', $afterFirst['status']);
        self::assertSame(6, $afterFirst['unitsPicked']);
        self::assertSame([true, false], $this->column($afterFirst, 'lines', 'confirmed'));

        $done = $this->confirm($list, 2, 'A-03-02', 4);
        $this->assertStatus(200);
        self::assertSame('completed', $done['status']);
        self::assertSame(10, $done['unitsPicked']);
        self::assertNotNull($done['completedAt']);

        self::assertSame('completed', $this->request('GET', '/api/waves/'.$this->id($wave))['status']);
        self::assertNotNull($this->request('GET', '/api/waves/'.$this->id($wave))['completedAt']);
        $picked = $this->request('GET', '/api/orders/'.$this->id($order));
        self::assertSame('picked', $picked['status']);
        self::assertSame(10, $this->lineOf($picked, 1)['pickedUnits']);
        self::assertSame(10, $this->lineOf($picked, 1)['allocatedUnits']);
        self::assertSame(0, $picked['shortageUnits']);
    }

    // STK-13: the units leave the shelves and wait at staging, still promised to the order
    public function testThePickedUnitsMoveToStagingAndStayPromised(): void
    {
        [$list, $order] = $this->listToWalk();
        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');

        $this->confirm($list, 1, 'A-03-01', 6);
        $this->confirm($list, 2, 'A-03-02', 4);

        self::assertSame(['onHand' => 0, 'reserved' => 0], $this->stockAt($this->first), 'Everything on the first shelf was taken.');
        self::assertSame(['onHand' => 2, 'reserved' => 0], $this->stockAt($this->second), 'What is left on the second shelf is free.');
        self::assertSame(['onHand' => 10, 'reserved' => 10], $this->stockAt($this->stagingId));

        $allocations = $this->allocationsOf($this->lineOf($this->request('GET', '/api/orders/'.$this->id($order)), 1)['reservationId']);
        self::assertCount(1, $allocations, 'The reservation now points at the units waiting at staging.');
        self::assertSame(10, $allocations[0]['units']);
        self::assertSame(2, $this->countRows("SELECT COUNT(*) FROM stock_movements WHERE type = 'transfer_out'"), 'Each pick is a transfer of the ledger.');
        self::assertSame(2, $this->countRows("SELECT COUNT(*) FROM stock_movements WHERE type = 'transfer_in'"));
    }

    // OUT-11
    public function testNothingChangesWhenTheScanDoesNotMatchTheLine(): void
    {
        [$list] = $this->listToWalk();
        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');

        $wrongLocation = $this->confirm($list, 1, 'A-03-02', 6);
        $this->assertProblem(422, 'outbound.pick_scan_mismatch', $wrongLocation);
        self::assertSame('location', $this->parameter($wrongLocation, 'what'));

        $wrongProduct = $this->confirm($list, 1, 'A-03-01', 6, gtin: self::YOGHURT_GTIN);
        $this->assertProblem(422, 'outbound.pick_scan_mismatch', $wrongProduct);
        self::assertSame('product', $this->parameter($wrongProduct, 'what'));

        $notABarcode = $this->confirm($list, 1, 'A-03-01', 6, gtin: '12345');
        $this->assertProblem(422, 'outbound.pick_scan_mismatch', $notABarcode);

        $unknown = $this->confirm($list, 1, 'A-03-01', 6, gtin: '8412345678936');
        $this->assertProblem(422, 'outbound.pick_scan_mismatch', $unknown);

        self::assertSame(['onHand' => 6, 'reserved' => 6], $this->stockAt($this->first), 'The stock did not move.');
        self::assertSame(['onHand' => 0, 'reserved' => 0], $this->stockAt($this->stagingId));
        self::assertSame([false, false], $this->column($this->request('GET', '/api/pick-lists/'.$this->id($list)), 'lines', 'confirmed'));
    }

    // OUT-11: the lot is scanned when the line has one
    public function testTheLotMustMatchWhenTheLineHasOne(): void
    {
        $this->stock($this->yoghurtId, $this->first, 8, 'L-77', '2028-03-31');
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->yoghurtId, 'units' => 8]]));
        $wave = $this->request('POST', '/api/waves', ['orderIds' => [$this->id($order)]]);
        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release');
        $listId = $this->listOf($released, 'pickListIds')[0];
        self::assertIsString($listId);
        $this->request('POST', '/api/pick-lists/'.$listId.'/start');

        $wrong = $this->request('POST', '/api/pick-lists/'.$listId.'/lines/1/confirm', ['location' => 'A-03-01', 'gtin' => self::YOGHURT_GTIN, 'units' => 8, 'lot' => 'L-99']);
        $this->assertProblem(422, 'outbound.pick_scan_mismatch', $wrong);
        self::assertSame('lot', $this->parameter($wrong, 'what'));

        $missing = $this->request('POST', '/api/pick-lists/'.$listId.'/lines/1/confirm', ['location' => 'A-03-01', 'gtin' => self::YOGHURT_GTIN, 'units' => 8]);
        $this->assertProblem(422, 'outbound.pick_scan_mismatch', $missing);

        $right = $this->request('POST', '/api/pick-lists/'.$listId.'/lines/1/confirm', ['location' => 'a-03-01', 'gtin' => self::YOGHURT_GTIN, 'units' => 8, 'lot' => 'L-77']);
        $this->assertStatus(200);
        self::assertSame('completed', $right['status']);
    }

    // OUT-10
    public function testAShortPickGivesUpWhatWasNotFound(): void
    {
        [$list, $order] = $this->listToWalk();
        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');

        $this->confirm($list, 1, 'A-03-01', 4, reason: 'Solo había cuatro en la balda');
        $this->assertStatus(200);
        $done = $this->confirm($list, 2, 'A-03-02', 4);

        self::assertSame('completed', $done['status']);
        self::assertSame('Solo había cuatro en la balda', $this->lineAt($done, 0)['shortReason']);
        $first = $this->lineAt($done, 0);
        self::assertIsInt($first['quantityToPick']);
        self::assertIsInt($first['quantityPicked']);
        self::assertSame(2, $first['quantityToPick'] - $first['quantityPicked']);
        $picked = $this->request('GET', '/api/orders/'.$this->id($order));
        self::assertSame('picked', $picked['status']);
        self::assertSame(8, $this->lineOf($picked, 1)['pickedUnits']);
        self::assertSame(8, $this->lineOf($picked, 1)['allocatedUnits'], 'What was not found is no longer allocated.');
        self::assertSame(2, $this->lineOf($picked, 1)['shortageUnits']);
        self::assertSame(2, $picked['shortageUnits']);
        // Four units were taken. The two that were not found are still on the shelf as far as the system knows, but nobody has promised them.
        self::assertSame(['onHand' => 2, 'reserved' => 0], $this->stockAt($this->first));
        self::assertSame(['onHand' => 8, 'reserved' => 8], $this->stockAt($this->stagingId));
    }

    public function testNothingFoundMovesNothingAndGivesEverythingUp(): void
    {
        [$list, $order] = $this->listToWalk();
        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');

        $this->confirm($list, 1, 'A-03-01', 0, reason: 'Balda vacía');
        $this->assertStatus(200);
        $this->confirm($list, 2, 'A-03-02', 4);

        self::assertSame(['onHand' => 6, 'reserved' => 0], $this->stockAt($this->first), 'Nothing was taken from the shelf, and nobody has promised what is on it.');
        self::assertSame(['onHand' => 4, 'reserved' => 4], $this->stockAt($this->stagingId));
        self::assertSame(4, $this->lineOf($this->request('GET', '/api/orders/'.$this->id($order)), 1)['allocatedUnits']);
    }

    // OUT-10
    public function testRefusesAPickThatBreaksTheQuantityRules(): void
    {
        [$list] = $this->listToWalk();
        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');

        $needsReason = $this->confirm($list, 1, 'A-03-01', 5);
        $this->assertProblem(422, 'outbound.short_pick_reason_required', $needsReason);
        self::assertSame(5, $this->parameter($needsReason, 'picked'));
        self::assertSame(6, $this->parameter($needsReason, 'toPick'));

        $this->assertProblem(422, 'outbound.pick_exceeds_requested', $this->confirm($list, 1, 'A-03-01', 7));
        $this->assertProblem(422, 'outbound.picked_quantity_invalid', $this->confirm($list, 1, 'A-03-01', -1, reason: 'x'));
        $this->assertProblem(422, 'outbound.short_pick_reason_invalid', $this->confirm($list, 1, 'A-03-01', 5, reason: '   '));
        self::assertSame(['onHand' => 6, 'reserved' => 6], $this->stockAt($this->first));
    }

    public function testEachStopIsConfirmedOnceAndMustExist(): void
    {
        [$list] = $this->listToWalk();
        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');
        $this->confirm($list, 1, 'A-03-01', 6);

        $this->assertProblem(409, 'outbound.pick_line_already_confirmed', $this->confirm($list, 1, 'A-03-01', 6));
        $this->assertProblem(404, 'outbound.pick_line_not_found', $this->confirm($list, 9, 'A-03-01', 1));
        self::assertSame(['onHand' => 6, 'reserved' => 6], $this->stockAt($this->stagingId), 'The second confirmation moved nothing.');
    }

    public function testPicksAreConfirmedOnlyOnAListInProgress(): void
    {
        [$list] = $this->listToWalk();

        $this->assertProblem(422, 'outbound.pick_list_not_in_progress', $this->confirm($list, 1, 'A-03-01', 6));

        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');
        $this->confirm($list, 1, 'A-03-01', 6);
        $this->confirm($list, 2, 'A-03-02', 4);
        $this->assertProblem(422, 'outbound.pick_list_not_in_progress', $this->confirm($list, 2, 'A-03-02', 4));
        $this->assertProblem(422, 'outbound.pick_list_not_in_progress', $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start'));
    }

    // OUT-12
    public function testASupervisorAssignsTheListAndOnlyThatOperatorCanWalkIt(): void
    {
        [$list] = $this->listToWalk();
        $someoneElse = Ids::next();

        $assigned = $this->request('POST', '/api/pick-lists/'.$this->id($list).'/assign', ['operatorId' => $someoneElse]);
        $this->assertStatus(200);
        self::assertSame($someoneElse, $assigned['operatorId']);
        self::assertSame('generated', $assigned['status']);

        $problem = $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');
        $this->assertProblem(409, 'outbound.pick_list_already_assigned', $problem);
        self::assertSame($someoneElse, $this->parameter($problem, 'operatorId'));
    }

    // OUT-12
    public function testTheListCanBeAssignedAgainUntilItIsStarted(): void
    {
        [$list] = $this->listToWalk();
        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/assign', ['operatorId' => Ids::next()]);
        $again = $this->request('POST', '/api/pick-lists/'.$this->id($list).'/assign', ['operatorId' => $this->me()]);
        self::assertSame($this->me(), $again['operatorId']);

        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');
        $this->assertStatus(200);

        $this->assertProblem(409, 'outbound.pick_list_already_started', $this->request('POST', '/api/pick-lists/'.$this->id($list).'/assign', ['operatorId' => Ids::next()]));
    }

    public function testAnOperatorWorksTheFloorButCannotAssign(): void
    {
        [$list] = $this->listToWalk();
        $this->role = Role::Operator;

        $this->assertProblem(403, 'shared.forbidden', $this->request('POST', '/api/pick-lists/'.$this->id($list).'/assign', ['operatorId' => Ids::next()]));

        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');
        $this->assertStatus(200);
        $this->confirm($list, 1, 'A-03-01', 6);
        $this->assertStatus(200);
    }

    public function testOnlyTheOperatorOfTheListConfirmsItsPicks(): void
    {
        [$list] = $this->listToWalk();
        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');
        $this->role = Role::Operator;

        $this->assertProblem(409, 'outbound.pick_list_already_assigned', $this->confirm($list, 1, 'A-03-01', 6));
    }

    public function testAListOfAnotherTenantCannotBeWalked(): void
    {
        [$list] = $this->listToWalk();
        $stranger = Ids::next();

        $this->assertProblem(404, 'outbound.pick_list_not_found', $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start', tenant: $stranger));
        $this->assertProblem(404, 'outbound.pick_list_not_found', $this->request('POST', '/api/pick-lists/'.$this->id($list).'/assign', ['operatorId' => Ids::next()], $stranger));
        $this->assertProblem(404, 'outbound.pick_list_not_found', $this->request('POST', '/api/pick-lists/'.$this->id($list).'/lines/1/confirm', ['location' => 'A-03-01', 'gtin' => self::SHIRT_GTIN, 'units' => 6], $stranger));
    }

    // The goods are off the shelves: the wave and the order can no longer be called off
    public function testAWaveWithPicksCannotBeCancelled(): void
    {
        [$list, , $wave] = $this->listToWalk();
        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');
        $this->confirm($list, 1, 'A-03-01', 6);

        $this->assertProblem(409, 'outbound.pick_list_already_picked', $this->request('POST', '/api/waves/'.$this->id($wave).'/cancel'));
        self::assertSame('released', $this->request('GET', '/api/waves/'.$this->id($wave))['status']);
    }

    public function testAWaveWhoseListsAreStartedButNotPickedCanBeCancelled(): void
    {
        [$list, $order, $wave] = $this->listToWalk();
        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');

        $this->request('POST', '/api/waves/'.$this->id($wave).'/cancel');
        $this->assertStatus(200);

        self::assertSame('cancelled', $this->request('GET', '/api/pick-lists/'.$this->id($list))['status']);
        self::assertSame('allocated', $this->request('GET', '/api/orders/'.$this->id($order))['status']);
    }

    public function testAPickedOrderCannotBeCancelled(): void
    {
        [$list, $order, $wave] = $this->listToWalk();
        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');
        $this->confirm($list, 1, 'A-03-01', 6);
        $this->confirm($list, 2, 'A-03-02', 4);

        $this->assertProblem(409, 'outbound.order_already_picked', $this->request('POST', '/api/orders/'.$this->id($order).'/cancel'));
        $this->assertProblem(409, 'outbound.wave_already_completed', $this->request('POST', '/api/waves/'.$this->id($wave).'/cancel'));
        self::assertSame(['onHand' => 10, 'reserved' => 10], $this->stockAt($this->stagingId), 'Nothing was released.');
    }

    // OUT-08
    public function testTheWaveIsCompleteOnlyWhenEveryListIs(): void
    {
        $this->stock($this->shirtId, $this->first, 6, received: '2026-09-01');
        $this->stock($this->shirtId, $this->second, 6, received: '2026-09-02');
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 12]]));
        $wave = $this->request('POST', '/api/waves', ['orderIds' => [$this->id($order)]]);
        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release', ['pickLists' => 2]);
        [$oneId, $twoId] = $this->listOf($released, 'pickListIds');
        self::assertIsString($oneId);
        self::assertIsString($twoId);
        $one = $this->request('GET', '/api/pick-lists/'.$oneId);
        $two = $this->request('GET', '/api/pick-lists/'.$twoId);
        $this->request('POST', '/api/pick-lists/'.$this->id($one).'/start');
        $this->request('POST', '/api/pick-lists/'.$this->id($two).'/start');

        $this->confirm($one, 1, 'A-03-01', 6);
        self::assertSame('released', $this->request('GET', '/api/waves/'.$this->id($wave))['status'], 'One list is done, the other is not.');
        self::assertSame('in_wave', $this->request('GET', '/api/orders/'.$this->id($order))['status']);

        $this->confirm($two, 1, 'A-03-02', 6);

        self::assertSame('completed', $this->request('GET', '/api/waves/'.$this->id($wave))['status']);
        self::assertSame('picked', $this->request('GET', '/api/orders/'.$this->id($order))['status']);
    }

    public function testNeedsAStagingLocationToTakeTheUnitsTo(): void
    {
        $elsewhere = $this->otherWarehouseLocationId;
        $this->stock($this->shirtId, $elsewhere, 5);
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 5]], ['warehouseId' => $this->otherWarehouseId]));
        $wave = $this->request('POST', '/api/waves', ['orderIds' => [$this->id($order)]]);
        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release');
        $listId = $this->listOf($released, 'pickListIds')[0];
        self::assertIsString($listId);
        $this->request('POST', '/api/pick-lists/'.$listId.'/start');

        $problem = $this->request('POST', '/api/pick-lists/'.$listId.'/lines/1/confirm', ['location' => 'A-01-01', 'gtin' => self::SHIRT_GTIN, 'units' => 5]);

        $this->assertProblem(422, 'outbound.no_staging_location', $problem);
        self::assertSame($this->otherWarehouseId, $this->parameter($problem, 'warehouseId'));
        self::assertSame(['onHand' => 5, 'reserved' => 5], $this->stockAt($elsewhere), 'The stock stayed where it was.');
        self::assertFalse($this->lineAt($this->request('GET', '/api/pick-lists/'.$listId), 0)['confirmed']);
    }

    public function testTheOpenApiDocumentHasTheFloorOperations(): void
    {
        $paths = $this->openApiPaths();

        foreach (['/api/pick-lists/{id}/assign', '/api/pick-lists/{id}/start', '/api/pick-lists/{id}/lines/{position}/confirm'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
    }

    public function testExplainsAMismatchInTheLanguageAsked(): void
    {
        [$list] = $this->listToWalk();
        $this->request('POST', '/api/pick-lists/'.$this->id($list).'/start');

        $this->acceptLanguage = 'en';
        self::assertSame('The location scanned does not match line 1 of the pick list.', $this->text($this->confirm($list, 1, 'Z-99-99', 6), 'detail'));

        $this->acceptLanguage = null;
        self::assertSame('La ubicación escaneada no coincide con la línea 1 de la hoja de picking.', $this->text($this->confirm($list, 1, 'Z-99-99', 6), 'detail'));
    }

    /**
     * An order of ten shirts, picked from two shelves (six and four), in a wave that is on the floor.
     * The second shelf holds six, so two stay behind. Returns the pick list, the order and the wave.
     *
     * @return array{array<array-key, mixed>, array<array-key, mixed>, array<array-key, mixed>}
     */
    private function listToWalk(): array
    {
        $this->stock($this->shirtId, $this->first, 6, received: '2026-09-01');
        $this->stock($this->shirtId, $this->second, 6, received: '2026-09-02');
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 10]]));
        self::assertSame('allocated', $order['status']);
        $wave = $this->request('POST', '/api/waves', ['orderIds' => [$this->id($order)]]);
        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release');

        $listId = $this->listOf($released, 'pickListIds')[0] ?? null;
        self::assertIsString($listId);

        return [$this->request('GET', '/api/pick-lists/'.$listId), $order, $wave];
    }

    /**
     * Confirms a stop with the barcode of the shirts unless another is given.
     *
     * @param array<array-key, mixed> $list
     *
     * @return array<array-key, mixed>
     */
    private function confirm(array $list, int $position, string $location, int $units, ?string $gtin = null, ?string $reason = null): array
    {
        return $this->request('POST', '/api/pick-lists/'.$this->id($list).'/lines/'.$position.'/confirm', array_filter([
            'location' => $location,
            'gtin' => $gtin ?? self::SHIRT_GTIN,
            'units' => $units,
            'reason' => $reason,
        ], static fn (mixed $value): bool => null !== $value));
    }

    private function me(): string
    {
        $id = $this->request('GET', '/api/me')['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    /**
     * What a location holds and has promised, in all its stock items.
     *
     * @return array{onHand: int, reserved: int}
     */
    private function stockAt(string $locationId): array
    {
        $row = $this->entityManager()->getConnection()->fetchAssociative('SELECT COALESCE(SUM(on_hand), 0) AS on_hand, COALESCE(SUM(reserved), 0) AS reserved FROM stock_items WHERE location_id = ?', [$locationId]);
        self::assertIsArray($row);

        \assert(is_numeric($row['on_hand']) && is_numeric($row['reserved']));

        return ['onHand' => (int) $row['on_hand'], 'reserved' => (int) $row['reserved']];
    }

    /**
     * The stock items that cover a reservation, with their units, and where each one is.
     *
     * @return list<array{stockItemId: string, units: int}>
     */
    private function allocationsOf(mixed $reservationId): array
    {
        self::assertIsString($reservationId);
        $json = $this->entityManager()->getConnection()->fetchOne('SELECT allocations FROM stock_reservations WHERE id = ?', [$reservationId]);
        self::assertIsString($json);

        $allocations = [];
        $rows = json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($rows);

        foreach ($rows as $row) {
            self::assertIsArray($row);
            self::assertIsString($row['stock_item_id'] ?? null);
            self::assertIsInt($row['units'] ?? null);
            $allocations[] = ['stockItemId' => $row['stock_item_id'], 'units' => $row['units']];
        }

        return $allocations;
    }

    private function countRows(string $sql): int
    {
        $count = $this->entityManager()->getConnection()->fetchOne($sql);
        \assert(is_numeric($count));

        return (int) $count;
    }

    /**
     * @param array<array-key, mixed> $resource
     *
     * @return list<mixed>
     */
    private function listOf(array $resource, string $field): array
    {
        $list = $resource[$field] ?? null;
        self::assertIsArray($list);

        return array_values($list);
    }

    /**
     * @param array<array-key, mixed> $resource
     *
     * @return list<mixed>
     */
    private function column(array $resource, string $field, string $key): array
    {
        return array_column($this->listOf($resource, $field), $key);
    }

    /**
     * @param array<array-key, mixed> $list
     *
     * @return array<array-key, mixed>
     */
    private function lineAt(array $list, int $index): array
    {
        $line = $this->listOf($list, 'lines')[$index] ?? null;
        self::assertIsArray($line);

        return $line;
    }
}
