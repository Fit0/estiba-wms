<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inbound\Domain;

use App\Inbound\Domain\Asn;
use App\Inbound\Domain\AsnLine;
use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\AsnStatus;
use App\Inbound\Domain\Event\AsnCancelled;
use App\Inbound\Domain\Event\AsnClosed;
use App\Inbound\Domain\Event\AsnRegistered;
use App\Inbound\Domain\Event\ReceiptOpened;
use App\Inbound\Domain\ExpectedItem;
use App\Inbound\Domain\ExpectedLine;
use App\Inbound\Domain\ItemCondition;
use App\Inbound\Domain\LotNumber;
use App\Inbound\Domain\ProductId;
use App\Inbound\Domain\Receipt;
use App\Inbound\Domain\ReceiptStatus;
use App\Inbound\Domain\ReceivableProduct;
use App\Inbound\Domain\SupplierName;
use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

final class AsnTest extends TestCase
{
    use AssertsDomainErrors;

    public function testRegistersAnAsnThatExpectsItsLines(): void
    {
        $tenantId = InboundMother::tenantId();
        $id = InboundMother::asnId();
        $product = InboundMother::product();

        $asn = Asn::register(
            $id,
            $tenantId,
            AsnReference::fromString(' ALB-2027-0153 '),
            SupplierName::fromString('Textiles del Sur'),
            new \DateTimeImmutable('2026-10-24T16:30:00+00:00'),
            [new ExpectedLine($product, 40, LotNumber::fromString('L-0425'), new \DateTimeImmutable('2027-03-31T12:00:00+00:00'))],
            new InMemoryAsnReferences(),
            InboundMother::at(),
        );

        self::assertTrue($asn->id()->equals($id));
        self::assertTrue($asn->tenantId()->equals($tenantId));
        self::assertSame('ALB-2027-0153', $asn->reference()->value);
        self::assertSame('Textiles del Sur', $asn->supplier()->value);
        self::assertSame('2026-10-24 00:00', $asn->expectedDate()->format('Y-m-d H:i'));
        self::assertSame(AsnStatus::Expected, $asn->status());
        self::assertNull($asn->openReceiptId());
        self::assertCount(1, $asn->lines());
        $line = $asn->lines()[0];
        self::assertTrue($line->productId->equals($product->id));
        self::assertSame(40, $line->expectedUnits);
        self::assertSame(0, $line->receivedUnits);
        self::assertSame('L-0425', $line->lot?->value);
        self::assertSame('2027-03-31 00:00', $line->expiryDate?->format('Y-m-d H:i'));

        $events = $asn->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AsnRegistered::class, $events[0]);
        self::assertSame('ALB-2027-0153', $events[0]->reference->value);
    }

    // INB-15
    public function testRejectsAnAsnWithoutLines(): void
    {
        self::assertDomainError('inbound.asn_lines_required', [], static fn (): Asn => InboundMother::asn([]));
    }

    // INB-15
    public function testRejectsAProductOnTwoLines(): void
    {
        $product = InboundMother::product();

        self::assertDomainError(
            'inbound.asn_product_repeated',
            ['productId' => $product->id->value],
            static fn (): Asn => InboundMother::asn([new ExpectedLine($product, 10), new ExpectedLine($product, 5)]),
        );
    }

    // INB-01
    public function testRejectsAProductThatIsBlockedOrDiscontinued(): void
    {
        $blocked = new ReceivableProduct(ProductId::fromString(Ids::next()), false, false, false);

        self::assertDomainError(
            'inbound.product_not_available',
            ['productId' => $blocked->id->value],
            static fn (): Asn => InboundMother::asn([new ExpectedLine($blocked, 10)]),
        );
    }

    // INB-02
    public function testRejectsALineThatExpectsNothing(): void
    {
        $product = InboundMother::product();

        self::assertDomainError(
            'inbound.expected_quantity_invalid',
            ['productId' => $product->id->value, 'units' => 0],
            static fn (): Asn => InboundMother::asn([new ExpectedLine($product, 0)]),
        );
    }

    // INB-03
    public function testRejectsAReferenceTheTenantAlreadyUsed(): void
    {
        $tenantId = InboundMother::tenantId();
        $registry = new InMemoryAsnReferences()->take($tenantId, 'ALB-2027-0153');

        self::assertDomainError('inbound.asn_reference_already_used', ['reference' => 'ALB-2027-0153'], static fn (): Asn => Asn::register(
            InboundMother::asnId(),
            $tenantId,
            AsnReference::fromString('ALB-2027-0153'),
            SupplierName::fromString('Textiles del Sur'),
            new \DateTimeImmutable('2026-10-24'),
            [new ExpectedLine(InboundMother::product(), 40)],
            $registry,
            InboundMother::at(),
        ));
    }

    public function testOpensAReceiptThatExpectsWhatIsStillToCome(): void
    {
        $product = InboundMother::product();
        $asn = InboundMother::asn([new ExpectedLine($product, 40)]);
        $receiptId = InboundMother::receiptId();
        $dock = InboundMother::dock();

        $receipt = $asn->openReceipt($receiptId, $dock, InboundMother::at());

        self::assertTrue($receipt->id()->equals($receiptId));
        self::assertTrue($receipt->asnId()->equals($asn->id()));
        self::assertTrue($receipt->tenantId()->equals($asn->tenantId()));
        self::assertTrue($receipt->dockLocationId()->equals($dock));
        self::assertSame(ReceiptStatus::Open, $receipt->status());
        self::assertCount(1, $receipt->expected());
        self::assertSame(40, $receipt->expected()[0]->units);
        self::assertNotNull($asn->openReceiptId());
        self::assertTrue($receiptId->equals($asn->openReceiptId()));
        self::assertInstanceOf(ReceiptOpened::class, $receipt->pullEvents()[0] ?? null);
    }

    // INB-16
    public function testOpensOneReceiptAtATime(): void
    {
        $asn = InboundMother::asn();
        $asn->openReceipt(InboundMother::receiptId(), InboundMother::dock(), InboundMother::at());

        self::assertDomainError('inbound.asn_receipt_already_open', ['reference' => 'ALB-2027-0153'], static fn (): Receipt => $asn->openReceipt(InboundMother::receiptId(), InboundMother::dock(), InboundMother::at()));
    }

    // Partial receipt: the second receipt expects only what the first one did not bring.
    public function testIsPartiallyReceivedUntilEveryLineIsCovered(): void
    {
        $shirts = InboundMother::product();
        $trousers = InboundMother::product();
        $asn = InboundMother::asn([new ExpectedLine($shirts, 40), new ExpectedLine($trousers, 10)]);

        $first = $asn->openReceipt(InboundMother::receiptId(), InboundMother::dock(), InboundMother::at());
        $first->receiveItem($shirts, 25, null, null, ItemCondition::Good, InboundMother::at());
        $first->receiveItem($trousers, 10, null, null, ItemCondition::Good, InboundMother::at());
        $asn->closeReceipt($first, InboundMother::at());

        self::assertSame(AsnStatus::PartiallyReceived, $asn->status());
        self::assertNull($asn->openReceiptId());
        self::assertSame([25, 10], array_map(static fn (AsnLine $line): int => $line->receivedUnits, $asn->lines()));

        $second = $asn->openReceipt(InboundMother::receiptId(), InboundMother::dock(), InboundMother::at());
        self::assertSame([15, 0], array_map(static fn (ExpectedItem $item): int => $item->units, $second->expected()));

        $second->receiveItem($shirts, 15, null, null, ItemCondition::Good, InboundMother::at());
        $asn->closeReceipt($second, InboundMother::at());

        self::assertSame(AsnStatus::Received, $asn->status());
        self::assertSame([40, 10], array_map(static fn (AsnLine $line): int => $line->receivedUnits, $asn->lines()));
    }

    // Damaged units count as received: they arrived, in quarantine (INB-10).
    public function testCountsDamagedUnitsAsReceived(): void
    {
        $product = InboundMother::product();
        [$asn, $receipt] = InboundMother::openReceipt(['p' => [$product, 10]]);
        $receipt->receiveItem($product, 8, null, null, ItemCondition::Good, InboundMother::at());
        $receipt->receiveItem($product, 2, null, null, ItemCondition::Damaged, InboundMother::at());

        $asn->closeReceipt($receipt, InboundMother::at());

        self::assertSame(AsnStatus::Received, $asn->status());
        self::assertSame(10, $asn->lines()[0]->receivedUnits);
    }

    // INB-05
    public function testClosesOnlyItsOwnOpenReceipt(): void
    {
        $asn = InboundMother::asn();
        [, $foreign] = InboundMother::openReceipt(['p' => [InboundMother::product(), 5]]);

        self::assertDomainError(
            'inbound.receipt_asn_mismatch',
            ['receiptId' => $foreign->id()->value, 'asnId' => $asn->id()->value],
            static fn () => $asn->closeReceipt($foreign, InboundMother::at()),
        );
        self::assertSame(ReceiptStatus::Open, $foreign->status());
    }

    // INB-04
    public function testACancelledAsnCannotBeReceivedAgainst(): void
    {
        $asn = InboundMother::asn();
        $asn->cancel(InboundMother::at());

        self::assertSame(AsnStatus::Cancelled, $asn->status());
        self::assertInstanceOf(AsnCancelled::class, $asn->pullEvents()[0] ?? null);
        self::assertDomainError('inbound.asn_not_receivable', ['reference' => 'ALB-2027-0153', 'status' => 'cancelled'], static fn (): Receipt => $asn->openReceipt(InboundMother::receiptId(), InboundMother::dock(), InboundMother::at()));
    }

    // INB-04
    public function testAClosedAsnCannotBeReceivedAgainst(): void
    {
        $product = InboundMother::product();
        [$asn, $receipt] = InboundMother::openReceipt(['p' => [$product, 10]]);
        $receipt->receiveItem($product, 6, null, null, ItemCondition::Good, InboundMother::at());
        $asn->closeReceipt($receipt, InboundMother::at());

        $asn->close(InboundMother::at());

        self::assertSame(AsnStatus::Closed, $asn->status());
        $closed = array_values(array_filter($asn->pullEvents(), static fn (object $event): bool => $event instanceof AsnClosed));
        self::assertCount(1, $closed);
        self::assertSame(AsnStatus::PartiallyReceived, $closed[0]->closedFrom, 'Closing a partially received ASN records the shortage.');
        self::assertDomainError('inbound.asn_not_receivable', ['reference' => 'ALB-2027-0153', 'status' => 'closed'], static fn () => $asn->openReceipt(InboundMother::receiptId(), InboundMother::dock(), InboundMother::at()));
    }

    public function testOnlyAnExpectedAsnCanBeCancelled(): void
    {
        $product = InboundMother::product();
        [$asn, $receipt] = InboundMother::openReceipt(['p' => [$product, 10]]);

        self::assertDomainError('inbound.asn_receipt_already_open', ['reference' => 'ALB-2027-0153'], static fn () => $asn->cancel(InboundMother::at()));

        $receipt->receiveItem($product, 4, null, null, ItemCondition::Good, InboundMother::at());
        $asn->closeReceipt($receipt, InboundMother::at());

        self::assertDomainError('inbound.asn_not_cancellable', ['reference' => 'ALB-2027-0153', 'status' => 'partially_received'], static fn () => $asn->cancel(InboundMother::at()));
    }

    public function testAnAsnThatReceivedNothingCannotBeClosed(): void
    {
        $asn = InboundMother::asn();

        self::assertDomainError('inbound.asn_not_closable', ['reference' => 'ALB-2027-0153', 'status' => 'expected'], static fn () => $asn->close(InboundMother::at()));
    }
}
