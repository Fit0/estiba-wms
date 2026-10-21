<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inbound\Domain;

use App\Inbound\Domain\Discrepancy;
use App\Inbound\Domain\DiscrepancyType;
use App\Inbound\Domain\Event\DiscrepancyDetected;
use App\Inbound\Domain\Event\ItemReceived;
use App\Inbound\Domain\Event\OverReceiptApproved;
use App\Inbound\Domain\Event\ReceiptClosed;
use App\Inbound\Domain\ItemCondition;
use App\Inbound\Domain\LotNumber;
use App\Inbound\Domain\Receipt;
use App\Inbound\Domain\ReceiptStatus;
use App\Inbound\Domain\ReceivableProduct;
use App\Shared\Domain\DomainEvent;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\TestCase;

final class ReceiptTest extends TestCase
{
    use AssertsDomainErrors;

    public function testRecordsWhatArrivesLineByLine(): void
    {
        $product = InboundMother::product();
        [, $receipt] = InboundMother::openReceipt(['p' => [$product, 40]]);

        $first = $receipt->receiveItem($product, 24, null, null, ItemCondition::Good, InboundMother::at());
        $second = $receipt->receiveItem($product, 16, null, null, ItemCondition::Good, InboundMother::at());

        self::assertSame(1, $first->lineNumber);
        self::assertSame(2, $second->lineNumber);
        self::assertSame([$first, $second], $receipt->lines());
        self::assertSame(40, $receipt->receivedUnitsOf($product->id));
        self::assertSame([], $receipt->discrepancies());
        self::assertSame(0, $receipt->overReceivedUnits());

        $events = $receipt->pullEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(ItemReceived::class, $events[0]);
        self::assertSame(24, $events[0]->units);
        self::assertSame(1, $events[0]->lineNumber);
    }

    public function testAShortageIsWhatHasNotArrivedYet(): void
    {
        $product = InboundMother::product();
        [, $receipt] = InboundMother::openReceipt(['p' => [$product, 40]]);

        $receipt->receiveItem($product, 30, null, null, ItemCondition::Good, InboundMother::at());

        self::assertEquals([new Discrepancy(DiscrepancyType::Shortage, $product->id, 10)], $receipt->discrepancies());
        self::assertSame(0, $receipt->overReceivedUnits(), 'A shortage needs no approval.');
    }

    // INB-08
    public function testMoreThanExpectedIsAnExcess(): void
    {
        $product = InboundMother::product();
        [, $receipt] = InboundMother::openReceipt(['p' => [$product, 40]]);

        $receipt->receiveItem($product, 42, null, null, ItemCondition::Good, InboundMother::at());

        self::assertEquals([new Discrepancy(DiscrepancyType::Excess, $product->id, 2)], $receipt->discrepancies());
        self::assertSame(2, $receipt->overReceivedUnits());
        self::assertSame([DiscrepancyType::Excess], $this->detected($receipt->pullEvents()));
    }

    // INB-06
    public function testAProductThatIsNotOnTheAsnIsRecordedAsUnexpected(): void
    {
        $expected = InboundMother::product();
        $stranger = InboundMother::product();
        [, $receipt] = InboundMother::openReceipt(['p' => [$expected, 40]]);

        $receipt->receiveItem($expected, 40, null, null, ItemCondition::Good, InboundMother::at());
        $receipt->receiveItem($stranger, 3, null, null, ItemCondition::Good, InboundMother::at());

        self::assertEquals([new Discrepancy(DiscrepancyType::UnexpectedProduct, $stranger->id, 3)], $receipt->discrepancies());
        self::assertSame(3, $receipt->overReceivedUnits());
        self::assertSame([DiscrepancyType::UnexpectedProduct], $this->detected($receipt->pullEvents()));
    }

    // INB-10
    public function testDamagedUnitsAreADiscrepancyOfTheirOwn(): void
    {
        $product = InboundMother::product();
        [, $receipt] = InboundMother::openReceipt(['p' => [$product, 40]]);

        $receipt->receiveItem($product, 37, null, null, ItemCondition::Good, InboundMother::at());
        $receipt->receiveItem($product, 2, null, null, ItemCondition::Damaged, InboundMother::at());
        $receipt->receiveItem($product, 1, null, null, ItemCondition::Damaged, InboundMother::at());

        self::assertEquals([new Discrepancy(DiscrepancyType::Damaged, $product->id, 3)], $receipt->discrepancies());
        self::assertSame(0, $receipt->overReceivedUnits(), 'Damaged units arrived as announced: no approval is needed.');
        self::assertSame([DiscrepancyType::Damaged, DiscrepancyType::Damaged], $this->detected($receipt->pullEvents()));
    }

    // INB-07
    public function testATrackedProductNeedsItsLotAndExpiryDate(): void
    {
        $product = InboundMother::trackedProduct();
        [, $receipt] = InboundMother::openReceipt(['p' => [$product, 10]]);
        $lot = LotNumber::fromString('L-0425');
        $expiry = new \DateTimeImmutable('2027-03-31');
        $parameters = ['productId' => $product->id->value];

        self::assertDomainError('inbound.lot_required', $parameters, static fn () => $receipt->receiveItem($product, 10, null, $expiry, ItemCondition::Good, InboundMother::at()));
        self::assertDomainError('inbound.expiry_date_required', $parameters, static fn () => $receipt->receiveItem($product, 10, $lot, null, ItemCondition::Good, InboundMother::at()));

        $line = $receipt->receiveItem($product, 10, $lot, $expiry, ItemCondition::Good, InboundMother::at());
        self::assertSame('L-0425', $line->lot?->value);
        self::assertSame('2027-03-31', $line->expiryDate?->format('Y-m-d'));
    }

    // INB-07, STK-07: data the product does not track would split its stock for nothing.
    public function testAnUntrackedProductTakesNoLotAndNoExpiryDate(): void
    {
        $product = InboundMother::product();
        [, $receipt] = InboundMother::openReceipt(['p' => [$product, 10]]);
        $parameters = ['productId' => $product->id->value];

        self::assertDomainError('inbound.lot_not_tracked', $parameters, static fn () => $receipt->receiveItem($product, 10, LotNumber::fromString('L-1'), null, ItemCondition::Good, InboundMother::at()));
        self::assertDomainError('inbound.expiry_date_not_tracked', $parameters, static fn () => $receipt->receiveItem($product, 10, null, new \DateTimeImmutable('2027-01-01'), ItemCondition::Good, InboundMother::at()));
        self::assertSame([], $receipt->lines());
    }

    // INB-07
    public function testRejectsGoodsThatHaveExpired(): void
    {
        $product = InboundMother::trackedProduct();
        [, $receipt] = InboundMother::openReceipt(['p' => [$product, 10]]);
        $lot = LotNumber::fromString('L-0425');

        self::assertDomainError('inbound.expiry_date_passed', ['expiryDate' => '2026-10-20'], static fn () => $receipt->receiveItem($product, 10, $lot, new \DateTimeImmutable('2026-10-20'), ItemCondition::Good, InboundMother::at()));

        $line = $receipt->receiveItem($product, 10, $lot, new \DateTimeImmutable('2026-10-21'), ItemCondition::Good, InboundMother::at());
        self::assertSame('2026-10-21', $line->expiryDate?->format('Y-m-d'), 'Goods that expire today can still be received.');
    }

    // INB-01
    public function testRejectsAProductThatWasBlockedSinceTheAsnArrived(): void
    {
        $product = InboundMother::product();
        [, $receipt] = InboundMother::openReceipt(['p' => [$product, 10]]);
        $blocked = new ReceivableProduct($product->id, false, false, false);

        self::assertDomainError('inbound.product_not_available', ['productId' => $product->id->value], static fn () => $receipt->receiveItem($blocked, 10, null, null, ItemCondition::Good, InboundMother::at()));
    }

    public function testRejectsACountOfNothing(): void
    {
        $product = InboundMother::product();
        [, $receipt] = InboundMother::openReceipt(['p' => [$product, 10]]);

        self::assertDomainError('inbound.received_quantity_invalid', ['units' => 0], static fn () => $receipt->receiveItem($product, 0, null, null, ItemCondition::Good, InboundMother::at()));
    }

    // INB-08
    public function testAnOverReceiptNeedsApprovalBeforeClosing(): void
    {
        $product = InboundMother::product();
        [$asn, $receipt] = InboundMother::openReceipt(['p' => [$product, 10]]);
        $receipt->receiveItem($product, 12, null, null, ItemCondition::Good, InboundMother::at());

        self::assertDomainError('inbound.over_receipt_not_approved', ['units' => 2], static fn () => $asn->closeReceipt($receipt, InboundMother::at()));
        self::assertSame(ReceiptStatus::Open, $receipt->status());

        $receipt->approveOverReceipt(InboundMother::at());
        $asn->closeReceipt($receipt, InboundMother::at());

        self::assertSame(ReceiptStatus::Closed, $receipt->status());
        self::assertTrue($receipt->isOverReceiptApproved());
        self::assertSame(12, $asn->lines()[0]->receivedUnits);
    }

    // INB-08
    public function testReceivingMoreAfterTheApprovalWithdrawsIt(): void
    {
        $product = InboundMother::product();
        [, $receipt] = InboundMother::openReceipt(['p' => [$product, 10]]);
        $receipt->receiveItem($product, 12, null, null, ItemCondition::Good, InboundMother::at());
        $receipt->approveOverReceipt(InboundMother::at());

        $receipt->receiveItem($product, 1, null, null, ItemCondition::Good, InboundMother::at());

        self::assertFalse($receipt->isOverReceiptApproved(), 'The supervisor did not see the last unit.');
    }

    // INB-08
    public function testAnApprovalStaysWhenWhatArrivesWasExpected(): void
    {
        $shirts = InboundMother::product();
        $trousers = InboundMother::product();
        [, $receipt] = InboundMother::openReceipt(['s' => [$shirts, 10], 't' => [$trousers, 10]]);
        $receipt->receiveItem($shirts, 12, null, null, ItemCondition::Good, InboundMother::at());
        $receipt->approveOverReceipt(InboundMother::at());

        $receipt->receiveItem($trousers, 10, null, null, ItemCondition::Good, InboundMother::at());

        self::assertTrue($receipt->isOverReceiptApproved());
    }

    // INB-08
    public function testApprovesOnlyAnOverReceiptAndOnlyOnce(): void
    {
        $product = InboundMother::product();
        [, $receipt] = InboundMother::openReceipt(['p' => [$product, 10]]);

        self::assertDomainError('inbound.over_receipt_missing', [], static fn () => $receipt->approveOverReceipt(InboundMother::at()));

        $receipt->receiveItem($product, 11, null, null, ItemCondition::Good, InboundMother::at());
        $receipt->pullEvents();
        $receipt->approveOverReceipt(InboundMother::at());

        self::assertInstanceOf(OverReceiptApproved::class, $receipt->pullEvents()[0] ?? null);
        self::assertDomainError('inbound.over_receipt_already_approved', [], static fn () => $receipt->approveOverReceipt(InboundMother::at()));
    }

    // INB-09
    public function testAClosedReceiptCannotChangeAndClosesOnce(): void
    {
        $product = InboundMother::product();
        [$asn, $receipt] = InboundMother::openReceipt(['p' => [$product, 10]]);
        $receipt->receiveItem($product, 10, null, null, ItemCondition::Good, InboundMother::at());
        $receipt->pullEvents();

        $asn->closeReceipt($receipt, InboundMother::at());

        self::assertSame(ReceiptStatus::Closed, $receipt->status());
        self::assertEquals(InboundMother::at(), $receipt->closedAt());
        $events = $receipt->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ReceiptClosed::class, $events[0]);
        self::assertTrue($events[0]->asnId->equals($asn->id()));

        $parameters = ['id' => $receipt->id()->value];
        self::assertDomainError('inbound.receipt_already_closed', $parameters, static fn () => $receipt->receiveItem($product, 1, null, null, ItemCondition::Good, InboundMother::at()));
        self::assertDomainError('inbound.receipt_already_closed', $parameters, static fn () => $receipt->approveOverReceipt(InboundMother::at()));
        self::assertDomainError('inbound.receipt_already_closed', $parameters, static fn () => $receipt->close(InboundMother::at()));
        self::assertSame([], $receipt->pullEvents());
    }

    public function testAProductWithNothingLeftToComeIsAnExcessNotAStranger(): void
    {
        $product = InboundMother::product();
        $receipt = $this->reopenedAfterReceivingAll($product);

        $receipt->receiveItem($product, 1, null, null, ItemCondition::Good, InboundMother::at());

        self::assertEquals([new Discrepancy(DiscrepancyType::Excess, $product->id, 1)], $receipt->discrepancies());
    }

    public function testUnknownProductsAreNotConfusedWithEachOther(): void
    {
        [, $receipt] = InboundMother::openReceipt(['p' => [InboundMother::product(), 1]]);
        $first = InboundMother::product();
        $second = InboundMother::product();

        $receipt->receiveItem($first, 2, null, null, ItemCondition::Good, InboundMother::at());
        $receipt->receiveItem($second, 3, null, null, ItemCondition::Good, InboundMother::at());
        $receipt->receiveItem($first, 1, null, null, ItemCondition::Good, InboundMother::at());

        $unexpected = array_values(array_filter($receipt->discrepancies(), static fn (Discrepancy $discrepancy): bool => DiscrepancyType::UnexpectedProduct === $discrepancy->type));
        self::assertEquals([
            new Discrepancy(DiscrepancyType::UnexpectedProduct, $first->id, 3),
            new Discrepancy(DiscrepancyType::UnexpectedProduct, $second->id, 3),
        ], $unexpected);
    }

    /**
     * A second receipt of an ASN whose first receipt brought everything it announced.
     */
    private function reopenedAfterReceivingAll(ReceivableProduct $product): Receipt
    {
        [$asn, $first] = InboundMother::openReceipt(['p' => [$product, 10]]);
        $first->receiveItem($product, 10, null, null, ItemCondition::Good, InboundMother::at());
        $asn->closeReceipt($first, InboundMother::at());

        return $asn->openReceipt(InboundMother::receiptId(), InboundMother::dock(), InboundMother::at());
    }

    /**
     * @param list<DomainEvent> $events
     *
     * @return list<DiscrepancyType>
     */
    private function detected(array $events): array
    {
        $types = [];
        foreach ($events as $event) {
            if ($event instanceof DiscrepancyDetected) {
                $types[] = $event->type;
            }
        }

        return $types;
    }
}
