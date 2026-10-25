<?php

declare(strict_types=1);

namespace App\Tests\Functional\Outbound\Api;

use App\Identity\Domain\Role;
use App\Tests\Unit\Support\Ids;

/**
 * Handing the parcels to a carrier: the shipment goes, the order is shipped and the stock leaves
 * the warehouse (OUT-03, OUT-24, OUT-25, STK-13, STK-16).
 */
final class DispatchApiTest extends ShipmentApiTestCase
{
    public function testDispatchesAPackedShipmentToACarrier(): void
    {
        $shipment = $this->packedShipment();

        $dispatched = $this->dispatch($shipment, 'SEUR', 'SE0042261187');

        $this->assertStatus(200);
        self::assertSame('dispatched', $dispatched['status']);
        self::assertSame('SEUR', $dispatched['carrier']);
        self::assertSame('SE0042261187', $dispatched['trackingNumber']);
        self::assertNotNull($dispatched['dispatchedAt']);
        self::assertSame($dispatched, $this->request('GET', '/api/shipments/'.$this->id($shipment)));
    }

    public function testTheTrackingNumberCanBeLeftOut(): void
    {
        $dispatched = $this->dispatch($this->packedShipment(), 'Correos Express');

        $this->assertStatus(200);
        self::assertNull($dispatched['trackingNumber']);
    }

    public function testTheOrderIsShippedWithWhatWasPicked(): void
    {
        $shipment = $this->packedShipment();

        $this->dispatch($shipment, 'SEUR');

        $order = $this->request('GET', '/api/orders/'.$this->text($shipment, 'orderId'));
        self::assertSame('shipped', $order['status']);
        self::assertSame(10, $this->lineOf($order, 1)['pickedUnits']);
        self::assertSame(10, $this->lineOf($order, 1)['shippedUnits']);
    }

    // STK-16: the units leave the warehouse, from where they waited
    public function testTheStockLeavesTheWarehouse(): void
    {
        $shipment = $this->packedShipment();
        self::assertSame(['onHand' => 10, 'reserved' => 10], $this->stockAt($this->stagingId), 'Before the dispatch the goods wait at staging.');

        $this->dispatch($shipment, 'SEUR');

        self::assertSame(['onHand' => 0, 'reserved' => 0], $this->stockAt($this->stagingId));
        self::assertSame(['onHand' => 0, 'reserved' => 0], $this->stockAt($this->shelf));
    }

    public function testTheLedgerShowsTheIssue(): void
    {
        $shipment = $this->packedShipment();

        $this->dispatch($shipment, 'SEUR');

        $issues = array_values(array_filter(
            $this->request('GET', '/api/stock-movements', query: ['productId' => $this->shirtId]),
            static fn (mixed $movement): bool => \is_array($movement) && 'issue' === ($movement['type'] ?? null),
        ));
        self::assertCount(1, $issues);
        self::assertSame(-10, $issues[0]['change']);
        self::assertSame(0, $issues[0]['balanceAfter']);
        self::assertSame('shipment:'.$this->id($shipment), $issues[0]['reference']);
        self::assertSame($this->stagingId, $issues[0]['locationId']);
    }

    // STK-13
    public function testTheReservationIsConsumed(): void
    {
        $shipment = $this->packedShipment();
        $order = $this->request('GET', '/api/orders/'.$this->text($shipment, 'orderId'));
        $reservationId = $this->lineOf($order, 1)['reservationId'];
        self::assertIsString($reservationId);
        self::assertSame('active', $this->reservationStatus($reservationId));

        $this->dispatch($shipment, 'SEUR');

        self::assertSame('consumed', $this->reservationStatus($reservationId));
    }

    // OUT-03: shipped never exceeds picked
    public function testAShortPickShipsOnlyWhatWasFound(): void
    {
        $this->stock($this->shirtId, $this->shelf, 10, received: '2026-09-01');
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 10]]));
        $wave = $this->request('POST', '/api/waves', ['orderIds' => [$this->id($order)]]);
        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release');
        $listId = $this->listOf($released, 'pickListIds')[0] ?? null;
        self::assertIsString($listId);
        $this->request('POST', '/api/pick-lists/'.$listId.'/start');
        $this->request('POST', '/api/pick-lists/'.$listId.'/lines/1/confirm', ['location' => 'A-03-01', 'gtin' => self::SHIRT_GTIN, 'units' => 7, 'reason' => 'Solo había siete en la balda']);
        $this->assertStatus(200);
        $shipment = $this->request('POST', '/api/shipments', ['orderId' => $this->id($order)]);
        $this->closeParcel($shipment, 7);
        $this->request('POST', '/api/shipments/'.$this->id($shipment).'/complete');

        $this->dispatch($shipment, 'SEUR');

        $this->assertStatus(200);
        $shipped = $this->request('GET', '/api/orders/'.$this->id($order));
        self::assertSame(7, $this->lineOf($shipped, 1)['shippedUnits']);
        self::assertSame(3, $shipped['shortageUnits']);
        self::assertSame(['onHand' => 0, 'reserved' => 0], $this->stockAt($this->stagingId));
        self::assertSame(['onHand' => 3, 'reserved' => 0], $this->stockAt($this->shelf), 'What was not found stays on the books until a count says otherwise.');
    }

    // OUT-25
    public function testAShipmentStillBeingPackedCannotBeDispatched(): void
    {
        $shipment = $this->startPacking();
        $this->closeParcel($shipment, 10);

        $problem = $this->dispatch($shipment, 'SEUR');

        $this->assertProblem(422, 'outbound.shipment_not_packed', $problem);
        self::assertSame(['onHand' => 10, 'reserved' => 10], $this->stockAt($this->stagingId), 'The goods have not left.');
    }

    // OUT-25
    public function testAShipmentIsDispatchedOnce(): void
    {
        $shipment = $this->packedShipment();
        $this->dispatch($shipment, 'SEUR');

        $this->assertProblem(409, 'outbound.shipment_already_dispatched', $this->dispatch($shipment, 'MRW'));
        self::assertSame('SEUR', $this->request('GET', '/api/shipments/'.$this->id($shipment))['carrier']);
        self::assertCount(1, $this->issues(), 'The stock was issued once.');
    }

    // OUT-24
    public function testNothingChangesInADispatchedShipment(): void
    {
        $shipment = $this->packedShipment();
        $this->dispatch($shipment, 'SEUR');

        $this->assertProblem(409, 'outbound.shipment_already_packed', $this->closeParcel($shipment, 1));
        $this->assertProblem(409, 'outbound.shipment_already_packed', $this->request('POST', '/api/shipments/'.$this->id($shipment).'/complete'));
    }

    // OUT-04
    public function testAShippedOrderCannotBeCancelled(): void
    {
        $shipment = $this->packedShipment();
        $this->dispatch($shipment, 'SEUR');

        $problem = $this->request('POST', '/api/orders/'.$this->text($shipment, 'orderId').'/cancel');

        $this->assertProblem(409, 'outbound.order_already_shipped', $problem);
        self::assertSame(['onHand' => 0, 'reserved' => 0], $this->stockAt($this->stagingId));
    }

    public function testNeedsACarrierAndATrackingNumberThatMakeSense(): void
    {
        $shipment = $this->packedShipment();

        $this->assertProblem(422, 'outbound.carrier_name_invalid', $this->dispatch($shipment, ''));
        $this->assertProblem(422, 'outbound.tracking_number_invalid', $this->dispatch($shipment, 'SEUR', str_repeat('9', 61)));

        self::assertSame('packed', $this->request('GET', '/api/shipments/'.$this->id($shipment))['status'], 'A refused dispatch changes nothing.');
        self::assertSame(['onHand' => 10, 'reserved' => 10], $this->stockAt($this->stagingId));
        self::assertSame([], $this->issues());
    }

    public function testReportsAShipmentThatDoesNotExist(): void
    {
        $this->assertProblem(404, 'outbound.shipment_not_found', $this->request('POST', '/api/shipments/'.Ids::next().'/dispatch', ['carrier' => 'SEUR']));
    }

    public function testOnlyASupervisorDispatches(): void
    {
        $shipment = $this->packedShipment();

        $this->role = Role::Operator;
        $this->dispatch($shipment, 'SEUR');
        $this->assertStatus(403);
        self::assertSame(['onHand' => 10, 'reserved' => 10], $this->stockAt($this->stagingId));

        $this->role = Role::Supervisor;
        $this->dispatch($shipment, 'SEUR');
        $this->assertStatus(200);
    }

    public function testAShipmentOfAnotherTenantCannotBeDispatched(): void
    {
        $shipment = $this->packedShipment();

        $problem = $this->request('POST', '/api/shipments/'.$this->id($shipment).'/dispatch', ['carrier' => 'SEUR'], Ids::next());

        $this->assertProblem(404, 'outbound.shipment_not_found', $problem);
        self::assertSame(['onHand' => 10, 'reserved' => 10], $this->stockAt($this->stagingId));
    }

    public function testDispatchedShipmentsCanBeListed(): void
    {
        $shipment = $this->packedShipment();
        $before = $this->request('GET', '/api/shipments', query: ['status' => 'dispatched']);

        $this->dispatch($shipment, 'SEUR');

        $dispatched = $this->request('GET', '/api/shipments', query: ['status' => 'dispatched']);
        $packed = $this->request('GET', '/api/shipments', query: ['status' => 'packed']);
        self::assertCount(0, $before);
        self::assertCount(1, $dispatched);
        self::assertCount(0, $packed);
    }

    public function testTheAuditTrailSaysWhoDispatchedAndWhatItDid(): void
    {
        $shipment = $this->packedShipment();
        $me = $this->id($this->request('GET', '/api/me'));

        $this->dispatch($shipment, 'SEUR');

        $trail = $this->request('GET', '/api/audit-log', query: ['subjectId' => $this->id($shipment)]);
        $dispatched = array_values(array_filter($trail, static fn (mixed $entry): bool => \is_array($entry) && 'outbound.shipment_dispatched' === ($entry['eventType'] ?? null)));
        self::assertCount(1, $dispatched);
        self::assertSame($me, $dispatched[0]['actorId']);
        self::assertIsArray($dispatched[0]['data']);
        self::assertSame('SEUR', $dispatched[0]['data']['carrier']);
        self::assertCount(1, $this->request('GET', '/api/audit-log', query: ['eventType' => 'outbound.order_shipped']));
    }

    public function testTheOpenApiDocumentHasTheDispatch(): void
    {
        self::assertArrayHasKey('/api/shipments/{id}/dispatch', $this->openApiPaths());
    }

    /**
     * A shipment of ten shirts with everything in one parcel and its packing complete.
     *
     * @return array<array-key, mixed>
     */
    private function packedShipment(): array
    {
        $shipment = $this->startPacking();
        $this->closeParcel($shipment, 10);
        $packed = $this->request('POST', '/api/shipments/'.$this->id($shipment).'/complete');
        $this->assertStatus(200);
        self::assertSame('packed', $packed['status']);

        return $packed;
    }

    /**
     * @param array<array-key, mixed> $shipment
     *
     * @return array<array-key, mixed>
     */
    private function dispatch(array $shipment, string $carrier, ?string $trackingNumber = null): array
    {
        return $this->request('POST', '/api/shipments/'.$this->id($shipment).'/dispatch', array_filter(['carrier' => $carrier, 'trackingNumber' => $trackingNumber], static fn (?string $value): bool => null !== $value));
    }

    private function reservationStatus(string $reservationId): string
    {
        $status = $this->entityManager()->getConnection()->fetchOne('SELECT status FROM stock_reservations WHERE id = ?', [$reservationId]);
        self::assertIsString($status);

        return $status;
    }

    /**
     * The movements that issued stock.
     *
     * @return list<mixed>
     */
    private function issues(): array
    {
        return array_values(array_filter(
            $this->request('GET', '/api/stock-movements', query: ['productId' => $this->shirtId]),
            static fn (mixed $movement): bool => \is_array($movement) && 'issue' === ($movement['type'] ?? null),
        ));
    }
}
