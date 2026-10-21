<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inbound\Api;

use App\Identity\Domain\Role;
use App\Tests\Unit\Support\Ids;

final class ReceiptApiTest extends InboundApiTestCase
{
    // Receiving is the work of the dock: an operator opens, counts and closes.
    public function testAnOperatorReceivesPartOfAnAsn(): void
    {
        $asn = $this->registerAsn();
        $this->role = Role::Operator;

        $receipt = $this->openReceipt($asn);
        $this->assertStatus(201);
        self::assertSame('open', $receipt['status']);
        self::assertSame($this->id($asn), $receipt['asnId']);
        self::assertSame($this->dockId, $receipt['dockLocationId']);
        self::assertSame([['productId' => $this->shirtId, 'units' => 40], ['productId' => $this->trousersId, 'units' => 10]], $receipt['expected']);
        self::assertSame($this->id($receipt), $this->request('GET', '/api/asns/'.$this->id($asn))['openReceiptId']);

        $this->receive($receipt, self::SHIRT_GTIN, 30);
        $this->assertStatus(200);
        $counted = $this->receive($receipt, self::SHIRT_GTIN, 10);
        self::assertSame([1, 2], array_column($this->listOf($counted, 'lines'), 'lineNumber'));
        self::assertSame([['type' => 'shortage', 'productId' => $this->trousersId, 'units' => 10]], $this->listOf($counted, 'discrepancies'));

        $closed = $this->request('POST', '/api/receipts/'.$this->id($receipt).'/close');
        $this->assertStatus(200);
        self::assertSame('closed', $closed['status']);
        self::assertIsString($closed['closedAt']);
        self::assertSame($closed, $this->request('GET', '/api/receipts/'.$this->id($receipt)));

        $after = $this->request('GET', '/api/asns/'.$this->id($asn));
        self::assertSame('partially_received', $after['status']);
        self::assertNull($after['openReceiptId']);
        self::assertSame([40, 0], array_column($this->listOf($after, 'lines'), 'receivedUnits'));
    }

    // Partial receipt: the trousers arrive in a second delivery.
    public function testASecondReceiptCompletesTheAsn(): void
    {
        $asn = $this->registerAsn();
        $first = $this->openReceipt($asn);
        $this->receive($first, self::SHIRT_GTIN, 40);
        $this->request('POST', '/api/receipts/'.$this->id($first).'/close');

        $second = $this->openReceipt($asn);
        self::assertSame([0, 10], array_column($this->listOf($second, 'expected'), 'units'));
        $this->receive($second, self::TROUSERS_GTIN, 10);
        $this->request('POST', '/api/receipts/'.$this->id($second).'/close');

        self::assertSame('received', $this->request('GET', '/api/asns/'.$this->id($asn))['status']);
    }

    // INB-16
    public function testAnAsnHasOneOpenReceiptAtATime(): void
    {
        $asn = $this->registerAsn();
        $this->openReceipt($asn);

        $problem = $this->openReceipt($asn);

        $this->assertProblem(409, 'inbound.asn_receipt_already_open', $problem);
    }

    // INB-04
    public function testACancelledAsnCannotBeReceived(): void
    {
        $asn = $this->registerAsn();
        $this->request('POST', '/api/asns/'.$this->id($asn).'/cancel');

        $problem = $this->openReceipt($asn);

        $this->assertProblem(422, 'inbound.asn_not_receivable', $problem);
        self::assertSame('cancelled', $this->parameter($problem, 'status'));
    }

    public function testAReceiptTakesPlaceAtAnActiveDock(): void
    {
        $asn = $this->registerAsn();

        $notADock = $this->openReceipt($asn, $this->reserveId);
        $this->assertProblem(422, 'inbound.dock_location_invalid', $notADock);
        self::assertSame('not_dock', $this->parameter($notADock, 'reason'));

        $unknown = $this->openReceipt($asn, Ids::next());
        $this->assertProblem(422, 'inbound.dock_location_invalid', $unknown);
        self::assertSame('unknown', $this->parameter($unknown, 'reason'));

        $this->request('POST', '/api/locations/'.$this->dockId.'/block', ['reason' => 'Puerta averiada']);
        $blocked = $this->openReceipt($asn);
        $this->assertProblem(422, 'inbound.dock_location_invalid', $blocked);
        self::assertSame('blocked', $this->parameter($blocked, 'reason'));
    }

    // INB-06: a product that is not on the ASN is recorded, not refused.
    public function testRecordsAnUnexpectedProduct(): void
    {
        $receipt = $this->openReceipt($this->registerAsn());

        $counted = $this->receive($receipt, self::STRANGER_GTIN, 3);

        $this->assertStatus(200);
        self::assertContains(['type' => 'unexpected_product', 'productId' => $this->strangerId, 'units' => 3], $this->listOf($counted, 'discrepancies'));
        self::assertSame(3, $counted['overReceivedUnits']);
    }

    public function testRejectsABarcodeNoProductCarries(): void
    {
        $receipt = $this->openReceipt($this->registerAsn());

        $unknown = $this->receive($receipt, '4006381333931', 1);
        $this->assertProblem(422, 'inbound.gtin_unknown', $unknown);

        $invalid = $this->receive($receipt, '1234', 1);
        $this->assertProblem(422, 'catalog.gtin_invalid', $invalid);
    }

    // INB-07
    public function testATrackedProductNeedsItsLotAndAFutureExpiryDate(): void
    {
        $asn = $this->registerAsn('ALB-1', [['productId' => $this->yoghurtId, 'units' => 12]]);
        $receipt = $this->openReceipt($asn);

        $withoutLot = $this->receive($receipt, self::YOGHURT_GTIN, 12, ['expiryDate' => '2099-03-31']);
        $this->assertProblem(422, 'inbound.lot_required', $withoutLot);

        $expired = $this->receive($receipt, self::YOGHURT_GTIN, 12, ['lot' => 'L-0425', 'expiryDate' => '2020-03-31']);
        $this->assertProblem(422, 'inbound.expiry_date_passed', $expired);
        self::assertSame('2020-03-31', $this->parameter($expired, 'expiryDate'));

        $counted = $this->receive($receipt, self::YOGHURT_GTIN, 12, ['lot' => 'L-0425', 'expiryDate' => '2099-03-31']);
        $this->assertStatus(200);
        self::assertSame('L-0425', $this->lineOf($counted, 0)['lot'] ?? null);
        self::assertSame('2099-03-31', $this->lineOf($counted, 0)['expiryDate'] ?? null);
    }

    // INB-08: only a supervisor lets an over-receipt through.
    public function testAnOverReceiptNeedsASupervisor(): void
    {
        $receipt = $this->openReceipt($this->registerAsn());
        $this->role = Role::Operator;
        $this->receive($receipt, self::SHIRT_GTIN, 42);
        $this->receive($receipt, self::TROUSERS_GTIN, 10);

        $refused = $this->request('POST', '/api/receipts/'.$this->id($receipt).'/close');
        $this->assertProblem(422, 'inbound.over_receipt_not_approved', $refused);
        self::assertSame(2, $this->parameter($refused, 'units'));

        $forbidden = $this->request('POST', '/api/receipts/'.$this->id($receipt).'/approve-over-receipt');
        $this->assertProblem(403, 'shared.forbidden', $forbidden);

        $this->role = Role::Supervisor;
        $approved = $this->request('POST', '/api/receipts/'.$this->id($receipt).'/approve-over-receipt');
        $this->assertStatus(200);
        self::assertTrue($approved['overReceiptApproved']);

        $this->role = Role::Operator;
        $closed = $this->request('POST', '/api/receipts/'.$this->id($receipt).'/close');
        $this->assertStatus(200);
        self::assertSame('closed', $closed['status']);
    }

    // INB-10
    public function testDamagedGoodsAreADiscrepancy(): void
    {
        $receipt = $this->openReceipt($this->registerAsn());

        $counted = $this->receive($receipt, self::SHIRT_GTIN, 2, ['condition' => 'damaged']);

        self::assertSame('damaged', $this->lineOf($counted, 0)['condition'] ?? null);
        self::assertContains(['type' => 'damaged', 'productId' => $this->shirtId, 'units' => 2], $this->listOf($counted, 'discrepancies'));
    }

    // INB-09
    public function testAClosedReceiptCannotChange(): void
    {
        $receipt = $this->openReceipt($this->registerAsn());
        $this->request('POST', '/api/receipts/'.$this->id($receipt).'/close');

        $problem = $this->receive($receipt, self::SHIRT_GTIN, 1);

        $this->assertProblem(409, 'inbound.receipt_already_closed', $problem);
    }

    public function testHidesTheReceiptOfAnotherTenant(): void
    {
        $receipt = $this->openReceipt($this->registerAsn());

        $problem = $this->request('GET', '/api/receipts/'.$this->id($receipt), null, Ids::next());

        $this->assertProblem(404, 'inbound.receipt_not_found', $problem);
    }

    public function testAnOperatorCannotChangeTheAsnItself(): void
    {
        $asn = $this->registerAsn();
        $this->role = Role::Operator;

        $problem = $this->request('POST', '/api/asns/'.$this->id($asn).'/cancel');

        $this->assertProblem(403, 'shared.forbidden', $problem);
    }
}
