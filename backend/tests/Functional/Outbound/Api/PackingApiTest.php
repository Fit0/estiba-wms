<?php

declare(strict_types=1);

namespace App\Tests\Functional\Outbound\Api;

use App\Identity\Domain\Role;
use App\Tests\Unit\Support\Ids;
use Symfony\Component\HttpFoundation\Response;

/**
 * Packing a picked order into parcels with their SSCC, and printing the labels (OUT-19 to OUT-24).
 */
final class PackingApiTest extends OutboundApiTestCase
{
    private string $shelf;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->shelf = $this->location($this->warehouseId, $this->zoneId, 'A-03-01', 'reserve', ['pickSequence' => 10]);
    }

    public function testStartsPackingAPickedOrder(): void
    {
        $order = $this->pickedOrder();

        $shipment = $this->request('POST', '/api/shipments', ['orderId' => $this->id($order)]);

        $this->assertStatus(201);
        self::assertSame('packing', $shipment['status']);
        self::assertSame($this->id($order), $shipment['orderId']);
        self::assertSame($this->warehouseId, $shipment['warehouseId']);
        self::assertSame([['productId' => $this->shirtId, 'units' => 10]], $shipment['picked']);
        self::assertSame(10, $shipment['unitsToPack']);
        self::assertSame([], $shipment['parcels']);
        self::assertNull($shipment['packedAt']);
        self::assertSame($shipment, $this->request('GET', '/api/shipments/'.$this->id($shipment)));
    }

    // OUT-20
    public function testOnlyAPickedOrderCanBePacked(): void
    {
        $this->stock($this->shirtId, $this->shelf, 10);
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 10]]));

        $problem = $this->request('POST', '/api/shipments', ['orderId' => $this->id($order)]);

        $this->assertProblem(422, 'outbound.order_not_picked', $problem);
        self::assertSame('allocated', $this->parameter($problem, 'status'));
    }

    // OUT-20
    public function testAnOrderHasOneShipment(): void
    {
        $order = $this->pickedOrder();
        $this->request('POST', '/api/shipments', ['orderId' => $this->id($order)]);

        $this->assertProblem(409, 'outbound.packing_already_started', $this->request('POST', '/api/shipments', ['orderId' => $this->id($order)]));
        self::assertCount(1, $this->request('GET', '/api/shipments'));
    }

    public function testReportsAnOrderThatDoesNotExist(): void
    {
        $this->assertProblem(404, 'outbound.order_not_found', $this->request('POST', '/api/shipments', ['orderId' => Ids::next()]));
    }

    // OUT-19
    public function testClosingAParcelAssignsItsSscc(): void
    {
        $shipment = $this->startPacking();

        $closed = $this->closeParcel($shipment, 6, 2300);

        $this->assertStatus(201);
        self::assertSame(4, $closed['unitsToPack']);
        self::assertCount(1, $this->parcelsOf($closed));
        $parcel = $this->parcelAt($closed, 0);
        self::assertSame(6, $parcel['units']);
        self::assertSame(2300, $parcel['weightGrams']);
        self::assertSame([['productId' => $this->shirtId, 'units' => 6]], $parcel['contents']);
        self::assertSame('084000000000000017', $parcel['sscc'], 'Extension 0, the company prefix, serial 1 and the GS1 check digit.');
        self::assertSame('packing', $closed['status']);
    }

    // OUT-19
    public function testEveryParcelGetsAnSsccOfItsOwn(): void
    {
        $shipment = $this->startPacking();
        $this->closeParcel($shipment, 6);

        $closed = $this->closeParcel($shipment, 4);

        $ssccs = array_map(static fn (array $parcel): string => \is_string($parcel['sscc'] ?? null) ? $parcel['sscc'] : '', $this->parcelsOf($closed));
        self::assertSame(['000000001', '000000002'], array_map(static fn (string $sscc): string => substr($sscc, 8, 9), $ssccs));
        foreach ($ssccs as $sscc) {
            self::assertSame(18, \strlen($sscc));
            self::assertSame($this->checkDigit(substr($sscc, 0, 17)), (int) substr($sscc, 17));
        }
    }

    // OUT-19: a parcel that is refused does not use up an SSCC
    public function testARefusedParcelGivesItsSerialBack(): void
    {
        $shipment = $this->startPacking();
        $this->assertProblem(422, 'outbound.packed_exceeds_picked', $this->closeParcel($shipment, 11));

        $closed = $this->closeParcel($shipment, 10);

        self::assertSame('084000000000000017', $this->parcelAt($closed, 0)['sscc']);
    }

    // OUT-21
    public function testRefusesAParcelThatIsEmptyOrWeighsNothing(): void
    {
        $shipment = $this->startPacking();
        $id = $this->id($shipment);

        $this->assertProblem(422, 'outbound.parcel_empty', $this->request('POST', '/api/shipments/'.$id.'/parcels', ['weightGrams' => 900, 'contents' => []]));
        $this->assertProblem(422, 'outbound.parcel_weight_invalid', $this->closeParcel($shipment, 5, 0));
        $this->assertProblem(422, 'outbound.parcel_weight_invalid', $this->closeParcel($shipment, 5, 1_000_001));
        $this->assertProblem(422, 'outbound.parcel_units_invalid', $this->closeParcel($shipment, 0));
        self::assertSame([], $this->request('GET', '/api/shipments/'.$id)['parcels']);
    }

    public function testRefusesContentsOfTheWrongShape(): void
    {
        $shipment = $this->startPacking();

        $this->request('POST', '/api/shipments/'.$this->id($shipment).'/parcels', ['weightGrams' => 900, 'contents' => [['productId' => $this->shirtId, 'units' => 'many']]]);
        $this->assertStatus(400);
        $this->request('POST', '/api/shipments/'.$this->id($shipment).'/parcels', ['weightGrams' => 900, 'contents' => ['shirts']]);
        $this->assertStatus(400);
    }

    // OUT-22
    public function testNothingThatWasNotPickedGoesInAParcel(): void
    {
        $shipment = $this->startPacking();

        $problem = $this->request('POST', '/api/shipments/'.$this->id($shipment).'/parcels', ['weightGrams' => 900, 'contents' => [['productId' => $this->yoghurtId, 'units' => 1]]]);

        $this->assertProblem(422, 'outbound.product_not_picked', $problem);
        self::assertSame($this->yoghurtId, $this->parameter($problem, 'productId'));
    }

    // OUT-22
    public function testNoMoreIsPackedThanWasPicked(): void
    {
        $shipment = $this->startPacking();
        $this->closeParcel($shipment, 7);

        $problem = $this->closeParcel($shipment, 4);

        $this->assertProblem(422, 'outbound.packed_exceeds_picked', $problem);
        self::assertSame(11, $this->parameter($problem, 'packed'));
        self::assertSame(10, $this->parameter($problem, 'picked'));
    }

    // OUT-23
    public function testPackingIsCompleteOnlyWhenEverythingPickedIsInAParcel(): void
    {
        $shipment = $this->startPacking();
        $this->closeParcel($shipment, 6);

        $problem = $this->request('POST', '/api/shipments/'.$this->id($shipment).'/complete');

        $this->assertProblem(422, 'outbound.packing_incomplete', $problem);
        self::assertSame(4, $this->parameter($problem, 'units'));
        self::assertSame('packing', $this->request('GET', '/api/shipments/'.$this->id($shipment))['status']);
    }

    public function testCompletesPackingAndLocksTheShipment(): void
    {
        $shipment = $this->startPacking();
        $this->closeParcel($shipment, 6);
        $this->closeParcel($shipment, 4);

        $packed = $this->request('POST', '/api/shipments/'.$this->id($shipment).'/complete');

        $this->assertStatus(200);
        self::assertSame('packed', $packed['status']);
        self::assertSame(0, $packed['unitsToPack']);
        self::assertNotNull($packed['packedAt']);
        $this->assertProblem(409, 'outbound.shipment_already_packed', $this->closeParcel($shipment, 1));
        $this->assertProblem(409, 'outbound.shipment_already_packed', $this->request('POST', '/api/shipments/'.$this->id($shipment).'/complete'));
    }

    public function testListsShipmentsByStatus(): void
    {
        $shipment = $this->startPacking();
        $this->closeParcel($shipment, 10);
        $this->request('POST', '/api/shipments/'.$this->id($shipment).'/complete');

        self::assertCount(1, $this->request('GET', '/api/shipments', query: ['status' => 'packed']));
        self::assertSame([], $this->request('GET', '/api/shipments', query: ['status' => 'packing']));
        $this->request('GET', '/api/shipments', query: ['status' => 'dispatched']);
        $this->assertStatus(400);
    }

    public function testThePdfLabelOfAParcel(): void
    {
        $shipment = $this->startPacking();
        $sscc = $this->sscc($this->closeParcel($shipment, 10, 2300));

        $response = $this->label($this->id($shipment), $sscc, 'pdf');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame(\sprintf('inline; filename="%s.pdf"', $sscc), $response->headers->get('Content-Disposition'));
        self::assertSame('es', $response->headers->get('Content-Language'));
        self::assertStringStartsWith('%PDF-1.4', (string) $response->getContent());
        self::assertStringContainsString('(Pedido PED-1) Tj', (string) $response->getContent());
    }

    public function testTheZplLabelOfAParcelInTheLanguageAsked(): void
    {
        $shipment = $this->startPacking();
        $sscc = $this->sscc($this->closeParcel($shipment, 10, 2300));

        $spanish = $this->label($this->id($shipment), $sscc, 'zpl');
        $this->acceptLanguage = 'en';
        $english = $this->label($this->id($shipment), $sscc, 'zpl');

        self::assertSame(Response::HTTP_OK, $spanish->getStatusCode());
        self::assertSame('text/plain; charset=utf-8', $spanish->headers->get('Content-Type'));
        self::assertStringContainsString('^FD>;>800'.$sscc.'^FS', (string) $spanish->getContent());
        self::assertStringContainsString('^FDMarea Moda Zaragoza^FS', (string) $spanish->getContent());
        self::assertStringContainsString('^FDCalle Mayor 12^FS', (string) $spanish->getContent());
        self::assertStringContainsString('^FDBulto 1^FS', (string) $spanish->getContent());
        self::assertStringContainsString('^FDPeso: 2.300 g^FS', (string) $spanish->getContent());
        self::assertStringContainsString('^FDParcel 1^FS', (string) $english->getContent());
        self::assertSame('en', $english->headers->get('Content-Language'));
    }

    public function testThereIsNoLabelForAParcelThatDoesNotExistOrAnSsccThatIsNotOne(): void
    {
        $shipment = $this->startPacking();
        $this->closeParcel($shipment, 10);

        $this->label($this->id($shipment), '084000000000000024', 'zpl');
        $this->assertProblem(404, 'outbound.parcel_not_found', $this->decodedResponse());
        $this->label($this->id($shipment), '084000000000000018', 'zpl');
        $this->assertProblem(422, 'outbound.sscc_invalid', $this->decodedResponse());
        $this->label(Ids::next(), '084000000000000017', 'zpl');
        $this->assertProblem(404, 'outbound.shipment_not_found', $this->decodedResponse());
    }

    public function testTheLabelHasOnlyTheFormatsThatExist(): void
    {
        $shipment = $this->startPacking();
        $sscc = $this->sscc($this->closeParcel($shipment, 10));

        $this->label($this->id($shipment), $sscc, 'png');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testAShipmentAndItsLabelsAreHiddenFromAnotherTenant(): void
    {
        $shipment = $this->startPacking();
        $sscc = $this->sscc($this->closeParcel($shipment, 10));
        $stranger = Ids::next();

        $this->assertProblem(404, 'outbound.shipment_not_found', $this->request('GET', '/api/shipments/'.$this->id($shipment), tenant: $stranger));
        $this->assertProblem(404, 'outbound.shipment_not_found', $this->request('POST', '/api/shipments/'.$this->id($shipment).'/complete', tenant: $stranger));
        $this->label($this->id($shipment), $sscc, 'zpl', $stranger);
        $this->assertProblem(404, 'outbound.shipment_not_found', $this->decodedResponse());
        self::assertSame([], $this->request('GET', '/api/shipments', tenant: $stranger));
    }

    public function testAnOperatorPacksButAnonymousCallersCannotEvenLook(): void
    {
        $order = $this->pickedOrder();
        $this->role = Role::Operator;

        $shipment = $this->request('POST', '/api/shipments', ['orderId' => $this->id($order)]);
        $this->assertStatus(201);
        $this->closeParcel($shipment, 10);
        $this->assertStatus(201);
        $this->request('POST', '/api/shipments/'.$this->id($shipment).'/complete');
        $this->assertStatus(200);
        $this->request('GET', '/api/shipments');
        $this->assertStatus(200);

        $this->request('GET', '/api/shipments', tenant: '');
        $this->assertStatus(401);
        $this->label($this->id($shipment), '084000000000000017', 'pdf', '');
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testExplainsAnErrorInTheLanguageAsked(): void
    {
        $shipment = $this->startPacking();

        $this->acceptLanguage = 'en';
        self::assertSame('Product '.$this->yoghurtId.' was not picked for this order.', $this->text($this->request('POST', '/api/shipments/'.$this->id($shipment).'/parcels', ['weightGrams' => 900, 'contents' => [['productId' => $this->yoghurtId, 'units' => 1]]]), 'detail'));

        $this->acceptLanguage = null;
        self::assertSame('El producto '.$this->yoghurtId.' no se recogió para este pedido.', $this->text($this->request('POST', '/api/shipments/'.$this->id($shipment).'/parcels', ['weightGrams' => 900, 'contents' => [['productId' => $this->yoghurtId, 'units' => 1]]]), 'detail'));
    }

    public function testTheOpenApiDocumentHasThePackingOperations(): void
    {
        $paths = $this->openApiPaths();

        foreach (['/api/shipments', '/api/shipments/{id}', '/api/shipments/{id}/parcels', '/api/shipments/{id}/complete'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
    }

    /**
     * An order of ten shirts, picked in full: allocated, in a wave, and its only stop confirmed.
     *
     * @return array<array-key, mixed>
     */
    private function pickedOrder(): array
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
    private function startPacking(): array
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
    private function closeParcel(array $shipment, int $units, int $weightGrams = 900): array
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
    private function parcelsOf(array $shipment): array
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
    private function parcelAt(array $shipment, int $index): array
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
    private function sscc(array $shipment): string
    {
        $parcels = $shipment['parcels'] ?? null;
        self::assertIsArray($parcels);
        $sscc = $this->parcelAt($shipment, \count($parcels) - 1)['sscc'] ?? null;
        self::assertIsString($sscc);

        return $sscc;
    }

    /**
     * The label of a parcel. It is not JSON, so it is read from the response and not decoded.
     */
    private function label(string $shipmentId, string $sscc, string $format, ?string $tenant = null): Response
    {
        $tenant ??= $this->tenant;
        $headers = ['HTTP_ACCEPT_LANGUAGE' => $this->acceptLanguage ?? ''];
        if ('' !== $tenant) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$this->tokenFor($tenant, $this->role);
        }

        $this->client->request('GET', \sprintf('/api/shipments/%s/parcels/%s/label.%s', $shipmentId, $sscc, $format), [], [], $headers);

        return $this->client->getResponse();
    }

    private function checkDigit(string $body): int
    {
        $sum = 0;
        $weight = 3;
        foreach (array_reverse(str_split($body)) as $digit) {
            $sum += $weight * (int) $digit;
            $weight = 4 - $weight;
        }

        return (10 - $sum % 10) % 10;
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
}
