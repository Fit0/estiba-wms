<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inbound\Api;

use App\Identity\Domain\Role;
use App\Tests\Unit\Support\Ids;

final class AsnApiTest extends InboundApiTestCase
{
    public function testRegistersAnAsnAndFindsIt(): void
    {
        $asn = $this->request('POST', '/api/asns', [
            'reference' => ' ALB-2027-0153 ',
            'supplier' => 'Textiles del Sur',
            'expectedDate' => '2026-10-24',
            'lines' => [
                ['productId' => $this->shirtId, 'units' => 40],
                ['productId' => $this->yoghurtId, 'units' => 12, 'lot' => 'L-0425', 'expiryDate' => '2027-03-31'],
            ],
        ]);

        $this->assertStatus(201);
        self::assertSame('ALB-2027-0153', $asn['reference']);
        self::assertSame('Textiles del Sur', $asn['supplier']);
        self::assertSame('2026-10-24', $asn['expectedDate']);
        self::assertSame('expected', $asn['status']);
        self::assertNull($asn['openReceiptId']);
        self::assertSame([
            ['productId' => $this->shirtId, 'expectedUnits' => 40, 'receivedUnits' => 0],
            ['productId' => $this->yoghurtId, 'expectedUnits' => 12, 'receivedUnits' => 0, 'lot' => 'L-0425', 'expiryDate' => '2027-03-31'],
        ], $asn['lines'], 'Empty lot and expiry date are left out of a line.');

        self::assertSame($asn, $this->request('GET', '/api/asns/'.$this->id($asn)));
    }

    // INB-15
    public function testRejectsAnAsnWithoutLines(): void
    {
        $problem = $this->registerAsn('ALB-1', []);

        $this->assertProblem(422, 'inbound.asn_lines_required', $problem);
    }

    // INB-01
    public function testRejectsAProductTheCatalogDoesNotHave(): void
    {
        $unknown = Ids::next();

        $problem = $this->registerAsn('ALB-1', [['productId' => $unknown, 'units' => 5]]);

        $this->assertProblem(422, 'inbound.product_unknown', $problem);
        self::assertSame($unknown, $this->parameter($problem, 'productId'));
    }

    // INB-01
    public function testRejectsABlockedProduct(): void
    {
        $this->request('POST', '/api/products/'.$this->shirtId.'/block');

        $problem = $this->registerAsn();

        $this->assertProblem(422, 'inbound.product_not_available', $problem);
    }

    // INB-01: a product of another tenant is unknown to this one.
    public function testRejectsAProductOfAnotherTenant(): void
    {
        $problem = $this->registerAsn('ALB-1', null, Ids::next());

        $this->assertProblem(422, 'inbound.product_unknown', $problem);
    }

    // INB-02
    public function testRejectsALineWithoutUnits(): void
    {
        $problem = $this->registerAsn('ALB-1', [['productId' => $this->shirtId, 'units' => 0]]);

        $this->assertProblem(422, 'inbound.expected_quantity_invalid', $problem);
        self::assertSame(0, $this->parameter($problem, 'units'));
    }

    // INB-03: importing the same ASN twice changes nothing.
    public function testRejectsAReferenceAlreadyUsed(): void
    {
        $this->registerAsn();

        $problem = $this->registerAsn();

        $this->assertProblem(409, 'inbound.asn_reference_already_used', $problem);
        self::assertCount(1, $this->request('GET', '/api/asns'));
    }

    public function testRejectsADateThatIsNotADate(): void
    {
        $this->request('POST', '/api/asns', ['reference' => 'ALB-1', 'supplier' => 'Textiles del Sur', 'expectedDate' => 'not a date', 'lines' => []]);

        $this->assertStatus(400);
    }

    public function testRejectsALineThatCannotBeRead(): void
    {
        $problem = $this->registerAsn('ALB-1', [['productId' => $this->shirtId, 'units' => 'many']]);
        $this->assertProblem(400, 'shared.request_malformed', $problem);

        $problem = $this->registerAsn('ALB-1', [['productId' => $this->shirtId, 'units' => 5, 'expiryDate' => '31/03/2027']]);
        $this->assertProblem(400, 'shared.request_malformed', $problem);
    }

    public function testOnlySupervisorsRegisterAsns(): void
    {
        $this->role = Role::Operator;

        $problem = $this->registerAsn();

        $this->assertProblem(403, 'shared.forbidden', $problem);
    }

    public function testHidesTheAsnOfAnotherTenant(): void
    {
        $asn = $this->registerAsn();

        $problem = $this->request('GET', '/api/asns/'.$this->id($asn), null, Ids::next());

        $this->assertProblem(404, 'inbound.asn_not_found', $problem);
    }

    public function testListsAsnsFilteredByStatus(): void
    {
        $this->registerAsn('ALB-1');
        $cancelled = $this->registerAsn('ALB-2');
        $this->request('POST', '/api/asns/'.$this->id($cancelled).'/cancel');

        $expected = $this->request('GET', '/api/asns', null, null, ['status' => 'expected']);
        $all = $this->request('GET', '/api/asns');

        self::assertSame(['ALB-1'], array_column($expected, 'reference'));
        self::assertSame(['ALB-1', 'ALB-2'], array_column($all, 'reference'));

        $this->request('GET', '/api/asns', null, null, ['status' => 'lost']);
        $this->assertStatus(400);
    }

    public function testCancelsAnAsnThatHasReceivedNothing(): void
    {
        $asn = $this->registerAsn();

        $cancelled = $this->request('POST', '/api/asns/'.$this->id($asn).'/cancel');

        $this->assertStatus(200);
        self::assertSame('cancelled', $cancelled['status']);
        $problem = $this->request('POST', '/api/asns/'.$this->id($asn).'/cancel');
        $this->assertProblem(422, 'inbound.asn_not_cancellable', $problem);
        self::assertSame('cancelled', $this->parameter($problem, 'status'));
    }

    // A shortage: the ASN closes with what arrived.
    public function testClosesAnAsnThatReceivedPartOfItsGoods(): void
    {
        $asn = $this->registerAsn();
        $receipt = $this->openReceipt($asn);
        $this->receive($receipt, self::SHIRT_GTIN, 40);
        $this->request('POST', '/api/receipts/'.$this->id($receipt).'/close');

        $closed = $this->request('POST', '/api/asns/'.$this->id($asn).'/close');

        $this->assertStatus(200);
        self::assertSame('closed', $closed['status']);
    }

    public function testAnAsnThatReceivedNothingCannotBeClosed(): void
    {
        $asn = $this->registerAsn();

        $problem = $this->request('POST', '/api/asns/'.$this->id($asn).'/close');

        $this->assertProblem(422, 'inbound.asn_not_closable', $problem);
    }

    public function testDescribesTheApiInOpenApi(): void
    {
        $paths = $this->openApiPaths();

        foreach (['/api/asns', '/api/asns/{id}', '/api/asns/{id}/cancel', '/api/asns/{id}/close', '/api/asns/{asnId}/receipts', '/api/receipts/{id}', '/api/receipts/{id}/items', '/api/receipts/{id}/approve-over-receipt', '/api/receipts/{id}/close'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
    }
}
