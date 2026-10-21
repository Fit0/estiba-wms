<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inbound\Api;

use App\Tests\Functional\Support\ApiTestCase;

/**
 * A catalog and a warehouse with a dock, made through the API, so the receiving tests start from
 * what a real tenant would have.
 */
abstract class InboundApiTestCase extends ApiTestCase
{
    protected const string SHIRT_GTIN = '8412345678905';

    protected const string TROUSERS_GTIN = '8412345678912';

    protected const string YOGHURT_GTIN = '8412345678929';

    protected const string STRANGER_GTIN = '8412345678936';

    protected string $shirtId;

    protected string $trousersId;

    protected string $yoghurtId;

    protected string $strangerId;

    protected string $dockId;

    protected string $reserveId;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->shirtId = $this->product('CAM-001', self::SHIRT_GTIN);
        $this->trousersId = $this->product('PAN-001', self::TROUSERS_GTIN);
        $this->yoghurtId = $this->product('YOG-001', self::YOGHURT_GTIN, ['trackedByLot' => true, 'trackedByExpiry' => true]);
        $this->strangerId = $this->product('CAL-001', self::STRANGER_GTIN);

        $warehouse = $this->request('POST', '/api/warehouses', ['code' => 'ZGZ1', 'name' => 'Zaragoza']);
        $zone = $this->request('POST', '/api/warehouses/'.$this->id($warehouse).'/zones', ['code' => 'IN', 'name' => 'Entradas', 'storageClass' => 'ambient']);
        $this->dockId = $this->location($this->id($warehouse), $this->id($zone), 'DK-01-01', 'dock');
        $this->reserveId = $this->location($this->id($warehouse), $this->id($zone), 'A-01-01', 'reserve');
    }

    /**
     * Registers an ASN of the shirts and the trousers, 40 and 10 units, unless other lines are given.
     *
     * @param list<array<string, mixed>>|null $lines
     *
     * @return array<array-key, mixed>
     */
    protected function registerAsn(string $reference = 'ALB-2027-0153', ?array $lines = null, ?string $tenant = null): array
    {
        return $this->request('POST', '/api/asns', [
            'reference' => $reference,
            'supplier' => 'Textiles del Sur',
            'expectedDate' => '2026-10-24',
            'lines' => $lines ?? [
                ['productId' => $this->shirtId, 'units' => 40],
                ['productId' => $this->trousersId, 'units' => 10],
            ],
        ], $tenant);
    }

    /**
     * @param array<array-key, mixed> $asn
     *
     * @return array<array-key, mixed>
     */
    protected function openReceipt(array $asn, ?string $locationId = null): array
    {
        return $this->request('POST', '/api/asns/'.$this->id($asn).'/receipts', ['dockLocationId' => $locationId ?? $this->dockId]);
    }

    /**
     * @param array<array-key, mixed> $receipt
     * @param array<string, mixed>    $extra
     *
     * @return array<array-key, mixed>
     */
    protected function receive(array $receipt, string $gtin, int $units, array $extra = []): array
    {
        return $this->request('POST', '/api/receipts/'.$this->id($receipt).'/items', ['gtin' => $gtin, 'units' => $units] + $extra);
    }

    /**
     * A list inside a resource, such as the `lines` of a receipt.
     *
     * @param array<array-key, mixed> $resource
     *
     * @return array<array-key, mixed>
     */
    protected function listOf(array $resource, string $field): array
    {
        $list = $resource[$field] ?? null;
        self::assertIsArray($list, \sprintf('The resource has no list "%s".', $field));

        return $list;
    }

    /**
     * One counted line of a receipt, from the first.
     *
     * @param array<array-key, mixed> $receipt
     *
     * @return array<array-key, mixed>
     */
    protected function lineOf(array $receipt, int $index): array
    {
        $line = $this->listOf($receipt, 'lines')[$index] ?? null;
        self::assertIsArray($line);

        return $line;
    }

    /**
     * @param array<string, mixed> $tracking
     */
    private function product(string $sku, string $gtin, array $tracking = []): string
    {
        $product = $this->request('POST', '/api/products', $tracking + [
            'sku' => $sku,
            'name' => 'Producto '.$sku,
            'baseUnit' => 'each',
            'storageClass' => 'ambient',
            'lengthMm' => 300,
            'widthMm' => 200,
            'heightMm' => 30,
            'weightGrams' => 250,
        ]);
        $this->request('POST', '/api/products/'.$this->id($product).'/gtins', ['gtin' => $gtin]);
        $this->assertStatus(200);

        return $this->id($product);
    }

    private function location(string $warehouseId, string $zoneId, string $code, string $type): string
    {
        return $this->id($this->request('POST', '/api/warehouses/'.$warehouseId.'/locations', ['zoneId' => $zoneId, 'code' => $code, 'type' => $type]));
    }
}
