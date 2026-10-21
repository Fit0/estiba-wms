<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Api;

use App\Tests\Functional\Support\ApiTestCase;
use App\Tests\Unit\Support\Ids;

final class ProductApiTest extends ApiTestCase
{
    private const string EAN_13 = '4006381333931';
    private const string UPC_12 = '036000291452';

    public function testRegistersAProduct(): void
    {
        $product = $this->register('agua-500', ['trackedByLot' => true, 'trackedByExpiry' => true, 'minShelfLifeAtReceiptDays' => 90]);

        $this->assertStatus(201);
        self::assertSame('AGUA-500', $product['sku']);
        self::assertSame('Agua mineral 500 ml', $product['name']);
        self::assertSame('each', $product['baseUnit']);
        self::assertSame('ambient', $product['storageClass']);
        self::assertSame('active', $product['status']);
        self::assertSame(120, $product['lengthMm']);
        self::assertSame(450, $product['weightGrams']);
        self::assertTrue($product['trackedByLot']);
        self::assertTrue($product['trackedByExpiry']);
        self::assertSame(90, $product['minShelfLifeAtReceiptDays']);
        self::assertNull($product['minShelfLifeAtShipmentDays']);
        self::assertSame([], $product['gtins']);
        self::assertSame([], $product['packagingLevels']);
    }

    // CAT-01: the SKU is unique per tenant, ignoring case.
    public function testRejectsADuplicateSkuInTheSameTenantButAllowsItInAnother(): void
    {
        $this->register('AGUA-500');

        $problem = $this->register(' agua-500 ');
        $this->assertProblem(409, 'catalog.sku_already_used', $problem);

        $this->register('AGUA-500', [], Ids::next());
        $this->assertStatus(201);
    }

    public function testRejectsAnInvalidSku(): void
    {
        $problem = $this->register('not valid!');

        $this->assertProblem(422, 'catalog.sku_invalid', $problem);
    }

    // CAT-04: a product tracked by expiry date is also tracked by lot.
    public function testRejectsTrackingByExpiryWithoutLot(): void
    {
        $problem = $this->register('LACTEO-1', ['trackedByExpiry' => true]);

        $this->assertProblem(422, 'catalog.expiry_requires_lot_tracking', $problem);
    }

    // CAT-07: a shelf life only applies to products tracked by expiry date.
    public function testRejectsAShelfLifeOnAProductNotTrackedByExpiry(): void
    {
        $problem = $this->register('LACTEO-1', ['trackedByLot' => true, 'minShelfLifeAtShipmentDays' => 10]);

        $this->assertProblem(422, 'catalog.shelf_life_invalid', $problem);
    }

    // CAT-09: dimensions and weight are positive.
    public function testRejectsADimensionThatIsNotPositive(): void
    {
        $problem = $this->register('AGUA-500', ['weightGrams' => 0]);

        $this->assertProblem(422, 'catalog.dimensions_invalid', $problem);
        self::assertSame('weightGrams', $this->parameter($problem, 'field'));
    }

    public function testRejectsAnUnknownBaseUnit(): void
    {
        $this->register('AGUA-500', ['baseUnit' => 'bucket']);

        $this->assertStatus(400);
    }

    public function testGetsAProductAndHidesItFromAnotherTenant(): void
    {
        $product = $this->register('AGUA-500');

        self::assertSame($product, $this->request('GET', '/api/products/'.$this->id($product)));
        $this->assertStatus(200);

        $problem = $this->request('GET', '/api/products/'.$this->id($product), null, Ids::next());
        $this->assertProblem(404, 'catalog.product_not_found', $problem);
    }

    public function testListsProductsPagedFilteredAndOrderedBySku(): void
    {
        foreach (['ACEITE-1', 'AGUA-500', 'AGUA-1500', 'ZUMO-1'] as $sku) {
            $this->register($sku);
        }
        $blocked = $this->register('LECHE-1');
        $this->request('POST', '/api/products/'.$this->id($blocked).'/block');
        $this->register('AGUA-OTRO', [], Ids::next());

        $all = $this->request('GET', '/api/products', null, null, ['itemsPerPage' => '3']);
        $lastPage = $this->request('GET', '/api/products', null, null, ['itemsPerPage' => '3', 'page' => '2']);
        $water = $this->request('GET', '/api/products', null, null, ['sku' => 'agua']);
        $onlyBlocked = $this->request('GET', '/api/products', null, null, ['status' => 'blocked']);

        self::assertSame(['ACEITE-1', 'AGUA-1500', 'AGUA-500'], array_column($all, 'sku'));
        self::assertSame(['LECHE-1', 'ZUMO-1'], array_column($lastPage, 'sku'));
        self::assertSame(['AGUA-1500', 'AGUA-500'], array_column($water, 'sku'));
        self::assertSame(['LECHE-1'], array_column($onlyBlocked, 'sku'));
    }

    public function testTreatsWildcardsInTheSkuFilterAsText(): void
    {
        $this->register('AGUA-500');

        $result = $this->request('GET', '/api/products', null, null, ['sku' => '%']);

        self::assertSame([], $result);
    }

    public function testRejectsAnUnknownStatusFilter(): void
    {
        $this->request('GET', '/api/products', null, null, ['status' => 'lost']);

        $this->assertStatus(400);
    }

    public function testAssignsGtinsToAProduct(): void
    {
        $product = $this->register('AGUA-500');

        $updated = $this->request('POST', '/api/products/'.$this->id($product).'/gtins', ['gtin' => self::EAN_13]);
        $this->assertStatus(200);
        $updated = $this->request('POST', '/api/products/'.$this->id($product).'/gtins', ['gtin' => '10614141000415']);

        self::assertSame([self::EAN_13, '10614141000415'], $updated['gtins']);
        self::assertSame($updated, $this->request('GET', '/api/products/'.$this->id($product)));
    }

    // CAT-02: every GTIN passes the GS1 check digit.
    public function testRejectsAGtinWithAWrongCheckDigit(): void
    {
        $product = $this->register('AGUA-500');

        $problem = $this->request('POST', '/api/products/'.$this->id($product).'/gtins', ['gtin' => '4006381333932']);

        $this->assertProblem(422, 'catalog.gtin_invalid', $problem);
    }

    // CAT-02: a GTIN belongs to one product of the tenant, whatever its printed length.
    public function testRejectsAGtinThatAnotherProductOfTheTenantHas(): void
    {
        $first = $this->register('AGUA-500');
        $second = $this->register('AGUA-1500');
        $this->request('POST', '/api/products/'.$this->id($first).'/gtins', ['gtin' => self::UPC_12]);

        $problem = $this->request('POST', '/api/products/'.$this->id($second).'/gtins', ['gtin' => '0'.self::UPC_12]);

        $this->assertProblem(409, 'catalog.gtin_already_used', $problem);
    }

    public function testAllowsTheSameGtinInAnotherTenant(): void
    {
        $otherTenant = Ids::next();
        $mine = $this->register('AGUA-500');
        $other = $this->register('AGUA-500', [], $otherTenant);
        $this->request('POST', '/api/products/'.$this->id($mine).'/gtins', ['gtin' => self::EAN_13]);

        $updated = $this->request('POST', '/api/products/'.$this->id($other).'/gtins', ['gtin' => self::EAN_13], $otherTenant);

        $this->assertStatus(200);
        self::assertSame([self::EAN_13], $updated['gtins']);
    }

    public function testDefinesPackagingLevelsFromSmallestToLargest(): void
    {
        $product = $this->register('AGUA-500');
        $path = '/api/products/'.$this->id($product).'/packaging-levels';

        $this->request('POST', $path, ['type' => 'case', 'baseUnits' => 24]);
        $updated = $this->request('POST', $path, ['type' => 'pallet', 'baseUnits' => 960]);

        $this->assertStatus(200);
        self::assertSame([['type' => 'case', 'baseUnits' => 24], ['type' => 'pallet', 'baseUnits' => 960]], $updated['packagingLevels']);
    }

    // CAT-03: each level is larger than the one before.
    public function testRejectsAPackagingLevelThatIsNotLarger(): void
    {
        $product = $this->register('AGUA-500');
        $path = '/api/products/'.$this->id($product).'/packaging-levels';
        $this->request('POST', $path, ['type' => 'case', 'baseUnits' => 24]);

        $problem = $this->request('POST', $path, ['type' => 'pallet', 'baseUnits' => 24]);

        $this->assertProblem(422, 'catalog.packaging_level_not_larger', $problem);
    }

    public function testUpdatesOnlyTheFieldsThatAreSent(): void
    {
        $product = $this->register('AGUA-500', ['trackedByLot' => true]);

        $updated = $this->request('PATCH', '/api/products/'.$this->id($product), ['name' => 'Agua 1 l', 'weightGrams' => 1050]);

        $this->assertStatus(200);
        self::assertSame('Agua 1 l', $updated['name']);
        self::assertSame(1050, $updated['weightGrams']);
        self::assertSame(120, $updated['lengthMm']);
        self::assertTrue($updated['trackedByLot']);
        self::assertSame($updated, $this->request('GET', '/api/products/'.$this->id($product)));
    }

    public function testRejectsAnUpdateWithADimensionThatIsNotPositive(): void
    {
        $product = $this->register('AGUA-500');

        $problem = $this->request('PATCH', '/api/products/'.$this->id($product), ['heightMm' => -5]);

        $this->assertProblem(422, 'catalog.dimensions_invalid', $problem);
    }

    // CAT-06 and CAT-08: blocking, unblocking and discontinuing.
    public function testBlocksUnblocksAndDiscontinuesAProduct(): void
    {
        $product = $this->register('AGUA-500');
        $base = '/api/products/'.$this->id($product);

        self::assertSame('blocked', $this->request('POST', $base.'/block')['status']);
        $this->assertStatus(200);
        self::assertSame('active', $this->request('POST', $base.'/unblock')['status']);
        self::assertSame('discontinued', $this->request('POST', $base.'/discontinue')['status']);
    }

    public function testRejectsBlockingAProductThatIsAlreadyBlocked(): void
    {
        $product = $this->register('AGUA-500');
        $this->request('POST', '/api/products/'.$this->id($product).'/block');

        $problem = $this->request('POST', '/api/products/'.$this->id($product).'/block');

        $this->assertProblem(409, 'catalog.product_already_blocked', $problem);
    }

    public function testRejectsUnblockingAProductThatIsNotBlocked(): void
    {
        $product = $this->register('AGUA-500');

        $problem = $this->request('POST', '/api/products/'.$this->id($product).'/unblock');

        $this->assertProblem(409, 'catalog.product_not_blocked', $problem);
    }

    public function testRejectsEveryChangeToADiscontinuedProduct(): void
    {
        $product = $this->register('AGUA-500');
        $base = '/api/products/'.$this->id($product);
        $this->request('POST', $base.'/discontinue');

        $problem = $this->request('POST', $base.'/block');
        $this->assertProblem(409, 'catalog.product_discontinued', $problem);

        $problem = $this->request('PATCH', $base, ['name' => 'Otro']);
        $this->assertProblem(409, 'catalog.product_discontinued', $problem);
    }

    public function testClassifiesAProductForPutaway(): void
    {
        $product = $this->register('CAM-001', ['family' => 'camiseria', 'turnoverClass' => 'A']);
        $this->assertStatus(201);
        self::assertSame('CAMISERIA', $product['family']);
        self::assertSame('A', $product['turnoverClass']);

        $plain = $this->register('CAM-002');
        self::assertNull($plain['family']);
        self::assertSame('B', $plain['turnoverClass'], 'Average pace until someone says otherwise.');

        $reclassified = $this->request('POST', '/api/products/'.$this->id($product).'/classification', ['turnoverClass' => 'C']);
        $this->assertStatus(200);
        self::assertNull($reclassified['family'], 'A family that is not sent is removed.');
        self::assertSame('C', $reclassified['turnoverClass']);
        self::assertSame($reclassified, $this->request('GET', '/api/products/'.$this->id($product)));

        $problem = $this->request('POST', '/api/products/'.$this->id($product).'/classification', ['family' => 'Camisería', 'turnoverClass' => 'A']);
        $this->assertProblem(422, 'shared.product_family_invalid', $problem);

        $this->request('POST', '/api/products/'.$this->id($product).'/classification', ['turnoverClass' => 'D']);
        $this->assertStatus(400);
    }

    public function testDescribesTheProductEndpointsInOpenApi(): void
    {
        $paths = $this->openApiPaths();

        foreach (['/api/products', '/api/products/{id}', '/api/products/{id}/gtins', '/api/products/{id}/classification', '/api/products/{id}/packaging-levels', '/api/products/{id}/block', '/api/products/{id}/unblock', '/api/products/{id}/discontinue'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<array-key, mixed>
     */
    private function register(string $sku, array $overrides = [], ?string $tenant = null): array
    {
        return $this->request('POST', '/api/products', $overrides + [
            'sku' => $sku,
            'name' => 'Agua mineral 500 ml',
            'baseUnit' => 'each',
            'storageClass' => 'ambient',
            'lengthMm' => 120,
            'widthMm' => 80,
            'heightMm' => 60,
            'weightGrams' => 450,
        ], $tenant);
    }
}
