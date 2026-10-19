<?php

declare(strict_types=1);

namespace App\Tests\Functional\Warehouse\Api;

use App\Tests\Functional\Support\ApiTestCase;
use App\Tests\Unit\Support\Ids;

final class LocationApiTest extends ApiTestCase
{
    private string $warehouseId;

    private string $zoneId;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $warehouse = $this->request('POST', '/api/warehouses', ['code' => 'MAD1', 'name' => 'Madrid']);
        $this->warehouseId = $this->id($warehouse);
        $zone = $this->request('POST', '/api/warehouses/'.$this->warehouseId.'/zones', ['code' => 'FAST', 'name' => 'Fast movers', 'storageClass' => 'ambient']);
        $this->zoneId = $this->id($zone);
    }

    public function testDefinesALocationAndFindsIt(): void
    {
        $location = $this->defineLocation('a-03-02', ['maxWeightKg' => 1000, 'maxPalletPositions' => 1]);

        $this->assertStatus(201);
        self::assertSame('A-03-02', $location['code']);
        self::assertSame($this->warehouseId, $location['warehouseId']);
        self::assertSame($this->zoneId, $location['zoneId']);
        self::assertSame('reserve', $location['type']);
        self::assertSame('active', $location['status']);
        self::assertNull($location['blockReason']);
        self::assertSame(1000, $location['maxWeightKg']);
        self::assertNull($location['maxVolumeLitres']);
        self::assertSame(1, $location['maxPalletPositions']);

        self::assertSame($location, $this->request('GET', '/api/locations/'.$this->id($location)));
    }

    // WH-03: the code is unique within the warehouse.
    public function testRejectsADuplicateLocationCode(): void
    {
        $this->defineLocation('A-03-02');

        $problem = $this->defineLocation('a-03-02');

        $this->assertProblem(409, 'warehouse.location_code_already_used', $problem);
    }

    public function testRejectsAnInvalidLocationCode(): void
    {
        $problem = $this->defineLocation('shelf 3');

        $this->assertProblem(422, 'warehouse.location_code_invalid', $problem);
    }

    // WH-04: capacity limits are positive whole numbers.
    public function testRejectsACapacityThatIsNotPositive(): void
    {
        $problem = $this->defineLocation('A-03-02', ['maxWeightKg' => 0]);

        $this->assertProblem(422, 'warehouse.capacity_invalid', $problem);
        self::assertSame('maxWeightKg', $this->parameter($problem, 'field'));
    }

    // WH-05: the zone belongs to the warehouse.
    public function testRejectsAZoneThatDoesNotBelongToTheWarehouse(): void
    {
        $problem = $this->request('POST', '/api/warehouses/'.$this->warehouseId.'/locations', ['zoneId' => Ids::next(), 'code' => 'A-03-02', 'type' => 'reserve']);

        $this->assertProblem(422, 'warehouse.zone_unknown', $problem);
    }

    public function testRejectsALocationInAnUnknownWarehouse(): void
    {
        $problem = $this->request('POST', '/api/warehouses/'.Ids::next().'/locations', ['zoneId' => $this->zoneId, 'code' => 'A-03-02', 'type' => 'reserve']);

        $this->assertProblem(404, 'warehouse.warehouse_not_found', $problem);
    }

    public function testHidesTheLocationOfAnotherTenant(): void
    {
        $location = $this->defineLocation('A-03-02');

        $problem = $this->request('GET', '/api/locations/'.$this->id($location), null, Ids::next());

        $this->assertProblem(404, 'warehouse.location_not_found', $problem);
    }

    public function testListsLocationsPagedAndOrderedByCode(): void
    {
        foreach (['A-01-01', 'A-01-02', 'A-01-03', 'B-01-01', 'B-01-02'] as $code) {
            $this->defineLocation($code);
        }
        $path = '/api/warehouses/'.$this->warehouseId.'/locations';

        $first = $this->request('GET', $path, null, null, ['itemsPerPage' => '2']);
        $this->assertStatus(200);
        self::assertSame(['A-01-01', 'A-01-02'], array_column($first, 'code'));

        $last = $this->request('GET', $path, null, null, ['itemsPerPage' => '2', 'page' => '3']);
        self::assertSame(['B-01-02'], array_column($last, 'code'));
    }

    public function testFiltersLocationsByZoneAndStatus(): void
    {
        $other = $this->request('POST', '/api/warehouses/'.$this->warehouseId.'/zones', ['code' => 'CHILL', 'name' => 'Chilled', 'storageClass' => 'chilled']);
        $this->defineLocation('A-01-01');
        $blocked = $this->defineLocation('A-01-02');
        $this->request('POST', '/api/locations/'.$this->id($blocked).'/block', ['reason' => 'Rack damaged']);
        $this->defineLocation('C-01-01', [], $this->id($other));
        $path = '/api/warehouses/'.$this->warehouseId.'/locations';

        $inChilled = $this->request('GET', $path, null, null, ['zoneId' => $this->id($other)]);
        $onlyBlocked = $this->request('GET', $path, null, null, ['status' => 'blocked']);

        self::assertSame(['C-01-01'], array_column($inChilled, 'code'));
        self::assertSame(['A-01-02'], array_column($onlyBlocked, 'code'));
    }

    public function testRejectsAnUnknownStatusFilter(): void
    {
        $this->request('GET', '/api/warehouses/'.$this->warehouseId.'/locations', null, null, ['status' => 'lost']);

        $this->assertStatus(400);
    }

    // WH-06: a blocked location is not eligible for storage until it is unblocked.
    public function testBlocksAndUnblocksALocation(): void
    {
        $location = $this->defineLocation('A-03-02');

        $blocked = $this->request('POST', '/api/locations/'.$this->id($location).'/block', ['reason' => ' Rack damaged by a forklift ']);
        $this->assertStatus(200);
        self::assertSame('blocked', $blocked['status']);
        self::assertSame('Rack damaged by a forklift', $blocked['blockReason']);
        self::assertSame($blocked, $this->request('GET', '/api/locations/'.$this->id($location)));

        $unblocked = $this->request('POST', '/api/locations/'.$this->id($location).'/unblock');
        $this->assertStatus(200);
        self::assertSame('active', $unblocked['status']);
        self::assertNull($unblocked['blockReason']);
    }

    public function testRejectsBlockingALocationThatIsAlreadyBlocked(): void
    {
        $location = $this->defineLocation('A-03-02');
        $this->request('POST', '/api/locations/'.$this->id($location).'/block', ['reason' => 'Inventory count']);

        $problem = $this->request('POST', '/api/locations/'.$this->id($location).'/block', ['reason' => 'Again']);

        $this->assertProblem(409, 'warehouse.location_already_blocked', $problem);
    }

    public function testRejectsUnblockingALocationThatIsNotBlocked(): void
    {
        $location = $this->defineLocation('A-03-02');

        $problem = $this->request('POST', '/api/locations/'.$this->id($location).'/unblock');

        $this->assertProblem(409, 'warehouse.location_not_blocked', $problem);
    }

    public function testRejectsABlockWithoutAReason(): void
    {
        $location = $this->defineLocation('A-03-02');

        $problem = $this->request('POST', '/api/locations/'.$this->id($location).'/block', ['reason' => '   ']);

        $this->assertProblem(422, 'warehouse.block_reason_invalid', $problem);
    }

    /**
     * @param array<string, mixed> $capacity
     *
     * @return array<array-key, mixed>
     */
    private function defineLocation(string $code, array $capacity = [], ?string $zoneId = null): array
    {
        return $this->request('POST', '/api/warehouses/'.$this->warehouseId.'/locations', [
            'zoneId' => $zoneId ?? $this->zoneId,
            'code' => $code,
            'type' => 'reserve',
            'pickSequence' => 10,
        ] + $capacity);
    }
}
