<?php

declare(strict_types=1);

namespace App\Tests\Functional\Outbound\Api;

/**
 * What the tests of packing and dispatching start from: a shelf, and orders picked in full that are
 * ready to be packed.
 */
abstract class ShipmentApiTestCase extends OutboundApiTestCase
{
    protected string $shelf;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->shelf = $this->location($this->warehouseId, $this->zoneId, 'A-03-01', 'reserve', ['pickSequence' => 10]);
    }

    /**
     * An order of ten shirts, picked in full: allocated, in a wave, and its only stop confirmed.
     *
     * @return array<array-key, mixed>
     */
    protected function pickedOrder(): array
    {
        $this->stock($this->shirtId, $this->shelf, 10, received: '2026-09-01');
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 10]]));
        $wave = $this->request('POST', '/api/waves', ['orderIds' => [$this->id($order)]]);
        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release');
        $listId = $this->listOf($released, 'pickListIds')[0] ?? null;
        self::assertIsString($listId);

        $this->request('POST', '/api/pick-lists/'.$listId.'/start');
        $this->request('POST', '/api/pick-lists/'.$listId.'/lines/1/confirm', ['location' => 'A-03-01', 'gtin' => self::SHIRT_GTIN, 'units' => 10]);
        $this->assertStatus(200);

        $picked = $this->request('GET', '/api/orders/'.$this->id($order));
        self::assertSame('picked', $picked['status']);

        return $picked;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function startPacking(): array
    {
        $shipment = $this->request('POST', '/api/shipments', ['orderId' => $this->id($this->pickedOrder())]);
        $this->assertStatus(201);

        return $shipment;
    }

    /**
     * Closes a parcel of shirts.
     *
     * @param array<array-key, mixed> $shipment
     *
     * @return array<array-key, mixed>
     */
    protected function closeParcel(array $shipment, int $units, int $weightGrams = 900): array
    {
        return $this->request('POST', '/api/shipments/'.$this->id($shipment).'/parcels', [
            'weightGrams' => $weightGrams,
            'contents' => [['productId' => $this->shirtId, 'units' => $units]],
        ]);
    }

    /**
     * @param array<array-key, mixed> $shipment
     *
     * @return list<array<array-key, mixed>>
     */
    protected function parcelsOf(array $shipment): array
    {
        $parcels = $shipment['parcels'] ?? null;
        self::assertIsArray($parcels);

        $list = [];
        foreach ($parcels as $parcel) {
            self::assertIsArray($parcel);
            $list[] = $parcel;
        }

        return $list;
    }

    /**
     * @param array<array-key, mixed> $shipment
     *
     * @return array<array-key, mixed>
     */
    protected function parcelAt(array $shipment, int $index): array
    {
        $parcels = $shipment['parcels'] ?? null;
        self::assertIsArray($parcels);
        $parcel = $parcels[$index] ?? null;
        self::assertIsArray($parcel);

        return $parcel;
    }

    /**
     * The SSCC of the last parcel of a shipment.
     *
     * @param array<array-key, mixed> $shipment
     */
    protected function sscc(array $shipment): string
    {
        $parcels = $shipment['parcels'] ?? null;
        self::assertIsArray($parcels);
        $sscc = $this->parcelAt($shipment, \count($parcels) - 1)['sscc'] ?? null;
        self::assertIsString($sscc);

        return $sscc;
    }

    /**
     * @param array<array-key, mixed> $resource
     *
     * @return list<mixed>
     */
    protected function listOf(array $resource, string $field): array
    {
        $list = $resource[$field] ?? null;
        self::assertIsArray($list);

        return array_values($list);
    }

    /**
     * What a location holds and has promised, in all its stock items.
     *
     * @return array{onHand: int, reserved: int}
     */
    protected function stockAt(string $locationId): array
    {
        $row = $this->entityManager()->getConnection()->fetchAssociative('SELECT COALESCE(SUM(on_hand), 0) AS on_hand, COALESCE(SUM(reserved), 0) AS reserved FROM stock_items WHERE location_id = ?', [$locationId]);
        self::assertIsArray($row);

        \assert(is_numeric($row['on_hand']) && is_numeric($row['reserved']));

        return ['onHand' => (int) $row['on_hand'], 'reserved' => (int) $row['reserved']];
    }
}
