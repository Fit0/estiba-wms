<?php

declare(strict_types=1);

namespace App\Tests\Functional\Warehouse\Api;

use App\Tests\Functional\Support\ApiTestCase;
use App\Tests\Unit\Support\Ids;

final class WarehouseApiTest extends ApiTestCase
{
    public function testCreatesAWarehouse(): void
    {
        $body = $this->request('POST', '/api/warehouses', ['code' => ' mad1 ', 'name' => 'Madrid central warehouse']);

        $this->assertStatus(201);
        self::assertSame('MAD1', $body['code']);
        self::assertSame('Madrid central warehouse', $body['name']);
        self::assertSame([], $body['zones']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $this->id($body));
    }

    // WH-01: the code format.
    public function testRejectsAnInvalidWarehouseCode(): void
    {
        $problem = $this->request('POST', '/api/warehouses', ['code' => 'not valid!', 'name' => 'Madrid']);

        $this->assertProblem(422, 'warehouse.warehouse_code_invalid', $problem);
        self::assertSame('not valid!', $this->parameter($problem, 'value'));
    }

    // WH-01: the code is unique per tenant.
    public function testRejectsADuplicateCodeInTheSameTenantButAllowsItInAnother(): void
    {
        $this->request('POST', '/api/warehouses', ['code' => 'MAD1', 'name' => 'Madrid']);

        $problem = $this->request('POST', '/api/warehouses', ['code' => 'mad1', 'name' => 'Madrid again']);
        $this->assertProblem(409, 'warehouse.warehouse_code_already_used', $problem);

        $this->request('POST', '/api/warehouses', ['code' => 'MAD1', 'name' => 'Madrid'], Ids::next());
        $this->assertStatus(201);
    }

    public function testRejectsABodyWithoutTheRequiredFields(): void
    {
        $problem = $this->request('POST', '/api/warehouses', ['code' => 'MAD1']);

        $this->assertProblem(400, 'shared.request_malformed', $problem);
    }

    public function testListsOnlyTheWarehousesOfTheTenantOrderedByCode(): void
    {
        $this->request('POST', '/api/warehouses', ['code' => 'MAD1', 'name' => 'Madrid']);
        $this->request('POST', '/api/warehouses', ['code' => 'BCN1', 'name' => 'Barcelona']);
        $this->request('POST', '/api/warehouses', ['code' => 'VLC1', 'name' => 'Valencia'], Ids::next());

        $list = $this->request('GET', '/api/warehouses');

        $this->assertStatus(200);
        self::assertSame(['BCN1', 'MAD1'], array_column($list, 'code'));
    }

    public function testGetsAWarehouseWithItsZones(): void
    {
        $warehouse = $this->request('POST', '/api/warehouses', ['code' => 'MAD1', 'name' => 'Madrid']);
        $this->request('POST', '/api/warehouses/'.$this->id($warehouse).'/zones', ['code' => 'FAST', 'name' => 'Fast movers', 'storageClass' => 'ambient', 'pickSequence' => 1]);

        $found = $this->request('GET', '/api/warehouses/'.$this->id($warehouse));

        $this->assertStatus(200);
        self::assertSame('MAD1', $found['code']);
        self::assertIsArray($found['zones']);
        self::assertSame(['FAST'], array_column($found['zones'], 'code'));
    }

    public function testReportsAnUnknownWarehouseAsNotFound(): void
    {
        $problem = $this->request('GET', '/api/warehouses/'.Ids::next());

        $this->assertProblem(404, 'warehouse.warehouse_not_found', $problem);
    }

    public function testHidesTheWarehouseOfAnotherTenant(): void
    {
        $warehouse = $this->request('POST', '/api/warehouses', ['code' => 'MAD1', 'name' => 'Madrid']);

        $problem = $this->request('GET', '/api/warehouses/'.$this->id($warehouse), null, Ids::next());

        $this->assertProblem(404, 'warehouse.warehouse_not_found', $problem);
    }

    public function testRejectsAMalformedIdentifier(): void
    {
        $problem = $this->request('GET', '/api/warehouses/not-a-uuid');

        $this->assertProblem(400, 'shared.identifier_invalid', $problem);
    }

    public function testRequiresASignedInUser(): void
    {
        $problem = $this->request('GET', '/api/warehouses', null, '');

        $this->assertProblem(401, 'shared.unauthenticated', $problem);
    }

    public function testDefinesAZoneAndFindsIt(): void
    {
        $warehouse = $this->request('POST', '/api/warehouses', ['code' => 'MAD1', 'name' => 'Madrid']);
        $path = '/api/warehouses/'.$this->id($warehouse).'/zones';

        $zone = $this->request('POST', $path, ['code' => 'chill', 'name' => 'Cámara de frío', 'storageClass' => 'chilled', 'pickSequence' => 2]);

        $this->assertStatus(201);
        self::assertSame('CHILL', $zone['code']);
        self::assertSame('Cámara de frío', $zone['name']);
        self::assertSame('chilled', $zone['storageClass']);
        self::assertSame(2, $zone['pickSequence']);

        $found = $this->request('GET', '/api/zones/'.$this->id($zone));
        $this->assertStatus(200);
        self::assertSame($zone, $found);
    }

    public function testAssignsTheFamiliesAZoneKeeps(): void
    {
        $warehouse = $this->request('POST', '/api/warehouses', ['code' => 'MAD1', 'name' => 'Madrid']);
        $zone = $this->request('POST', '/api/warehouses/'.$this->id($warehouse).'/zones', ['code' => 'MODA', 'name' => 'Moda', 'storageClass' => 'ambient']);
        self::assertSame([], $zone['families']);

        $assigned = $this->request('POST', '/api/zones/'.$this->id($zone).'/families', ['families' => ['punto', 'CAMISERIA']]);

        $this->assertStatus(200);
        self::assertSame(['CAMISERIA', 'PUNTO'], $assigned['families']);
        self::assertSame($assigned, $this->request('GET', '/api/zones/'.$this->id($zone)));

        $problem = $this->request('POST', '/api/zones/'.$this->id($zone).'/families', ['families' => ['NOT VALID']]);
        $this->assertProblem(422, 'shared.product_family_invalid', $problem);

        $this->request('POST', '/api/zones/'.$this->id($zone).'/families', ['families' => [7]]);
        $this->assertStatus(400);

        $unknown = $this->request('POST', '/api/zones/'.Ids::next().'/families', ['families' => []]);
        $this->assertProblem(404, 'warehouse.zone_not_found', $unknown);
    }

    // WH-02: the zone code is unique within the warehouse.
    public function testRejectsADuplicateZoneCode(): void
    {
        $warehouse = $this->request('POST', '/api/warehouses', ['code' => 'MAD1', 'name' => 'Madrid']);
        $path = '/api/warehouses/'.$this->id($warehouse).'/zones';
        $this->request('POST', $path, ['code' => 'FAST', 'name' => 'Fast movers', 'storageClass' => 'ambient']);

        $problem = $this->request('POST', $path, ['code' => 'FAST', 'name' => 'Other', 'storageClass' => 'ambient']);

        $this->assertProblem(409, 'warehouse.zone_code_already_used', $problem);
    }

    public function testRejectsAnUnknownStorageClass(): void
    {
        $warehouse = $this->request('POST', '/api/warehouses', ['code' => 'MAD1', 'name' => 'Madrid']);

        $this->request('POST', '/api/warehouses/'.$this->id($warehouse).'/zones', ['code' => 'FAST', 'name' => 'Fast', 'storageClass' => 'lava']);

        $this->assertStatus(400);
    }

    public function testReportsAnUnknownZoneAsNotFound(): void
    {
        $this->request('POST', '/api/warehouses', ['code' => 'MAD1', 'name' => 'Madrid']);

        $problem = $this->request('GET', '/api/zones/'.Ids::next());

        $this->assertProblem(404, 'warehouse.zone_not_found', $problem);
    }

    public function testDescribesTheApiInOpenApi(): void
    {
        $paths = $this->openApiPaths();

        foreach (['/api/warehouses', '/api/warehouses/{id}', '/api/warehouses/{warehouseId}/zones', '/api/warehouses/{warehouseId}/locations', '/api/locations/{id}', '/api/locations/{id}/block', '/api/locations/{id}/unblock', '/api/zones/{id}', '/api/zones/{id}/families'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
    }
}
