<?php

declare(strict_types=1);

namespace App\Tests\Functional\Flow;

use App\Identity\Domain\Role;
use App\Tests\Functional\Support\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The whole life of a garment through the API (E04-06): it is ordered from a supplier, arrives at the
 * dock, is counted, put away on a shelf, sold to a store, picked, packed in parcels with their SSCC
 * labels and dispatched to a carrier. Every step is a request from the person who would make it, and
 * at the end the stock, the order, the ledger and the audit trail all tell the same story.
 */
final class WarehouseFlowTest extends ApiTestCase
{
    private const string GTIN = '8412345678905';

    public function testAGarmentGoesFromTheSupplierToTheStore(): void
    {
        // The supervisor lays out the warehouse and the catalog.
        $this->role = Role::Supervisor;
        $warehouse = $this->id($this->request('POST', '/api/warehouses', ['code' => 'ZGZ-01', 'name' => 'Centro logístico de Zaragoza']));
        $zone = $this->id($this->request('POST', '/api/warehouses/'.$warehouse.'/zones', ['code' => 'A', 'name' => 'Moda', 'storageClass' => 'ambient', 'pickSequence' => 10]));
        $dock = $this->location($warehouse, $zone, 'DK-01-01', 'dock');
        $shelf = $this->location($warehouse, $zone, 'A-01-01', 'reserve', 10);
        $staging = $this->location($warehouse, $zone, 'E-01-01', 'staging');
        $product = $this->id($this->request('POST', '/api/products', [
            'sku' => 'CAM-LIN-BL-M', 'name' => 'Camisa de lino blanca, talla M', 'baseUnit' => 'each', 'storageClass' => 'ambient',
            'lengthMm' => 300, 'widthMm' => 220, 'heightMm' => 30, 'weightGrams' => 250,
        ]));
        $this->request('POST', '/api/products/'.$product.'/gtins', ['gtin' => self::GTIN]);
        $this->assertStatus(200);

        // 1. A delivery of 40 shirts is announced, arrives and is counted at the dock.
        $asn = $this->id($this->request('POST', '/api/asns', [
            'reference' => 'ALB-2026-0153', 'supplier' => 'Textiles del Ebro', 'expectedDate' => '2026-10-26',
            'lines' => [['productId' => $product, 'units' => 40]],
        ]));
        $this->assertStatus(201);
        $this->role = Role::Operator;
        $receipt = $this->id($this->request('POST', '/api/asns/'.$asn.'/receipts', ['dockLocationId' => $dock]));
        $this->assertStatus(201);
        $this->request('POST', '/api/receipts/'.$receipt.'/items', ['gtin' => self::GTIN, 'units' => 40]);
        $this->assertStatus(200);
        $closed = $this->request('POST', '/api/receipts/'.$receipt.'/close');
        $this->assertStatus(200);
        self::assertSame('closed', $closed['status']);
        self::assertSame(['onHand' => 40, 'reserved' => 0], $this->stockAt($dock), 'The stock is booked at the dock.');

        // 2. The shirts are put away on the shelf the system suggests.
        $tasks = $this->listIn($this->request('GET', '/api/putaway-tasks', query: ['receiptId' => $receipt]));
        self::assertCount(1, $tasks);
        self::assertSame($shelf, $tasks[0]['suggestedLocationId']);
        $this->request('POST', '/api/putaway-tasks/'.$this->idOf($tasks[0]).'/confirm', ['locationId' => $shelf]);
        $this->assertStatus(200);
        self::assertSame(['onHand' => 0, 'reserved' => 0], $this->stockAt($dock));
        self::assertSame(['onHand' => 40, 'reserved' => 0], $this->stockAt($shelf));

        // 3. A store orders 24 shirts. The supervisor reserves the stock and sends the wave to the floor.
        $this->role = Role::Supervisor;
        $order = $this->id($this->request('POST', '/api/orders', [
            'reference' => 'PED-2026-1187', 'warehouseId' => $warehouse, 'customer' => 'Marea Moda Zaragoza',
            'shipTo' => ['street' => 'Calle Mayor 12', 'postalCode' => '50001', 'city' => 'Zaragoza', 'countryCode' => 'ES'],
            'cutOffAt' => '2026-10-28T14:00:00+00:00', 'priority' => 'normal',
            'lines' => [['productId' => $product, 'units' => 24]],
        ]));
        $this->assertStatus(201);
        $allocated = $this->request('POST', '/api/orders/'.$order.'/allocate');
        self::assertSame('allocated', $allocated['status']);
        self::assertSame(['onHand' => 40, 'reserved' => 24], $this->stockAt($shelf), 'The units are promised and still on the shelf.');
        $wave = $this->id($this->request('POST', '/api/waves', ['orderIds' => [$order]]));
        $released = $this->request('POST', '/api/waves/'.$wave.'/release');
        $this->assertStatus(200);
        $pickListId = $this->idOf($this->listFrom($released['pickListIds'] ?? null)[0] ?? null);

        // 4. An operator walks the pick list and confirms the stop with a scan.
        $this->role = Role::Operator;
        $this->request('POST', '/api/pick-lists/'.$pickListId.'/start');
        $this->assertStatus(200);
        $done = $this->request('POST', '/api/pick-lists/'.$pickListId.'/lines/1/confirm', ['location' => 'A-01-01', 'gtin' => self::GTIN, 'units' => 24]);
        $this->assertStatus(200);
        self::assertSame('completed', $done['status']);
        self::assertSame('completed', $this->request('GET', '/api/waves/'.$wave)['status']);
        self::assertSame('picked', $this->request('GET', '/api/orders/'.$order)['status']);
        self::assertSame(['onHand' => 16, 'reserved' => 0], $this->stockAt($shelf));
        self::assertSame(['onHand' => 24, 'reserved' => 24], $this->stockAt($staging), 'The picked units wait at staging, still promised.');

        // 5. They are packed in two parcels, each with its SSCC and its label.
        $shipment = $this->id($this->request('POST', '/api/shipments', ['orderId' => $order]));
        $this->assertStatus(201);
        $this->request('POST', '/api/shipments/'.$shipment.'/parcels', ['weightGrams' => 3800, 'contents' => [['productId' => $product, 'units' => 14]]]);
        $packing = $this->request('POST', '/api/shipments/'.$shipment.'/parcels', ['weightGrams' => 2700, 'contents' => [['productId' => $product, 'units' => 10]]]);
        $this->assertStatus(201);
        $ssccs = array_column($this->listIn($this->listFrom($packing['parcels'] ?? null)), 'sscc');
        self::assertCount(2, $ssccs);
        self::assertNotSame($ssccs[0], $ssccs[1]);
        $packed = $this->request('POST', '/api/shipments/'.$shipment.'/complete');
        $this->assertStatus(200);
        self::assertSame('packed', $packed['status']);
        foreach ($ssccs as $sscc) {
            self::assertIsString($sscc);
            self::assertStringContainsString('^FD>;>800'.$sscc.'^FS', $this->label($shipment, $sscc));
        }

        // 6. The supervisor hands the parcels to the carrier, and the goods leave.
        $this->role = Role::Supervisor;
        $dispatched = $this->request('POST', '/api/shipments/'.$shipment.'/dispatch', ['carrier' => 'SEUR', 'trackingNumber' => 'SE0042261187']);
        $this->assertStatus(200);
        self::assertSame('dispatched', $dispatched['status']);

        // The order, the stock, the ledger and the trail agree.
        $shipped = $this->request('GET', '/api/orders/'.$order);
        self::assertSame('shipped', $shipped['status']);
        self::assertSame(24, $this->firstLine($shipped)['shippedUnits']);
        self::assertSame(['onHand' => 0, 'reserved' => 0], $this->stockAt($staging), 'Nothing is left at staging.');
        self::assertSame(['onHand' => 16, 'reserved' => 0], $this->stockAt($shelf), 'The shelf keeps what nobody bought.');
        $stock = $this->request('GET', '/api/products/'.$product.'/stock');
        self::assertSame(16, $stock['onHand']);
        self::assertSame(16, $stock['available']);

        self::assertSame(
            ['issue' => 1, 'receipt' => 1, 'transfer_in' => 2, 'transfer_out' => 2],
            $this->movementsOf($product),
            'The ledger: the delivery, the putaway and the pick as transfers, and the dispatch as an issue.',
        );
        $this->assertTheTrailTellsTheStory($this->expectedTrail());
    }

    /**
     * The events of the story, in the order they happened.
     *
     * @return list<string>
     */
    private function expectedTrail(): array
    {
        return [
            'warehouse.warehouse_created',
            'catalog.product_registered',
            'catalog.gtin_assigned',
            'inbound.asn_registered',
            'inbound.receipt_opened',
            'inbound.item_received',
            'inbound.receipt_closed',
            'inbound.putaway_task_planned',
            'inbound.putaway_confirmed',
            'outbound.order_registered',
            'outbound.order_allocated',
            'outbound.wave_created',
            'outbound.pick_list_generated',
            'outbound.wave_released',
            'outbound.pick_list_started',
            'outbound.pick_line_confirmed',
            'outbound.pick_list_completed',
            'outbound.order_picked',
            'outbound.wave_completed',
            'outbound.packing_started',
            'outbound.parcel_closed',
            'outbound.parcel_closed',
            'outbound.packing_completed',
            'outbound.shipment_dispatched',
            'outbound.order_shipped',
        ];
    }

    /**
     * The audit trail has every event of the story, once each, and its milestones in the order they
     * happened. (Events published together by one request are not compared among themselves: only
     * the milestones, which come from different requests, are.).
     *
     * @param list<string> $story
     */
    private function assertTheTrailTellsTheStory(array $story): void
    {
        $types = [];
        foreach ($this->listIn($this->request('GET', '/api/audit-log', query: ['itemsPerPage' => 100])) as $entry) {
            $type = $entry['eventType'] ?? null;
            if (\is_string($type) && \in_array($type, $story, true)) {
                $types[] = $type;
            }
        }
        $types = array_reverse($types);

        $milestones = ['warehouse.warehouse_created', 'inbound.asn_registered', 'inbound.receipt_closed', 'inbound.putaway_confirmed', 'outbound.order_registered', 'outbound.order_allocated', 'outbound.wave_released', 'outbound.pick_line_confirmed', 'outbound.packing_started', 'outbound.packing_completed', 'outbound.shipment_dispatched'];
        $only = static fn (array $list): array => array_values(array_filter($list, static fn (mixed $type): bool => \in_array($type, $milestones, true)));

        self::assertSame($only($story), $only($types), 'The milestones are in order.');
        self::assertSame(array_count_values($story), array_count_values($types), 'Every event of the story is in the trail, and only once.');
    }

    /**
     * How many movements of each type the product has had.
     *
     * @return array<string, int>
     */
    private function movementsOf(string $productId): array
    {
        $counts = [];
        foreach ($this->listIn($this->request('GET', '/api/stock-movements', query: ['productId' => $productId, 'itemsPerPage' => 100])) as $movement) {
            $type = $movement['type'] ?? null;
            self::assertIsString($type);
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    private function location(string $warehouseId, string $zoneId, string $code, string $type, int $pickSequence = 0): string
    {
        return $this->id($this->request('POST', '/api/warehouses/'.$warehouseId.'/locations', ['zoneId' => $zoneId, 'code' => $code, 'type' => $type, 'pickSequence' => $pickSequence]));
    }

    /**
     * What a location holds and has promised, in all its stock items.
     *
     * @return array{onHand: int, reserved: int}
     */
    private function stockAt(string $locationId): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $row = $entityManager->getConnection()->fetchAssociative('SELECT COALESCE(SUM(on_hand), 0) AS on_hand, COALESCE(SUM(reserved), 0) AS reserved FROM stock_items WHERE location_id = ?', [$locationId]);
        self::assertIsArray($row);
        \assert(is_numeric($row['on_hand']) && is_numeric($row['reserved']));

        return ['onHand' => (int) $row['on_hand'], 'reserved' => (int) $row['reserved']];
    }

    /**
     * The ZPL label of a parcel, which is text and not JSON.
     */
    private function label(string $shipmentId, string $sscc): string
    {
        $this->client->request('GET', \sprintf('/api/shipments/%s/parcels/%s/label.zpl', $shipmentId, $sscc), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor($this->tenant, $this->role),
        ]);
        $this->assertStatus(200);

        return (string) $this->client->getResponse()->getContent();
    }

    /**
     * @param array<array-key, mixed> $order
     *
     * @return array<array-key, mixed>
     */
    private function firstLine(array $order): array
    {
        $lines = $order['lines'] ?? null;
        self::assertIsArray($lines);
        $line = $lines[0] ?? null;
        self::assertIsArray($line);

        return $line;
    }

    /**
     * @param array<array-key, mixed> $list
     *
     * @return list<array<array-key, mixed>>
     */
    private function listIn(array $list): array
    {
        $items = [];
        foreach ($list as $item) {
            self::assertIsArray($item);
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return list<mixed>
     */
    private function listFrom(mixed $value): array
    {
        self::assertIsArray($value);

        return array_values($value);
    }

    private function idOf(mixed $value): string
    {
        if (\is_array($value)) {
            $value = $value['id'] ?? null;
        }
        self::assertIsString($value);

        return $value;
    }
}
