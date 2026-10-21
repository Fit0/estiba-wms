<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

use App\Inbound\Domain\Event\AsnCancelled;
use App\Inbound\Domain\Event\AsnClosed;
use App\Inbound\Domain\Event\AsnRegistered;
use App\Inbound\Domain\Exception\AsnNotReceivable;
use App\Inbound\Domain\Exception\AsnReferenceAlreadyUsed;
use App\Inbound\Domain\Exception\AsnStatusNotAllowed;
use App\Inbound\Domain\Exception\InvalidAsnLines;
use App\Inbound\Domain\Exception\InvalidExpectedQuantity;
use App\Inbound\Domain\Exception\ProductNotReceivable;
use App\Inbound\Domain\Exception\ReceiptAlreadyOpen;
use App\Inbound\Domain\Exception\ReceiptAsnMismatch;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;

/**
 * An advance shipping notice: what a supplier says it will deliver, and how much of it has
 * arrived so far.
 *
 * Goods are received against an ASN through receipts, one at a time (INB-16). A receipt is opened
 * and closed through the ASN ({@see openReceipt()}, {@see closeReceipt()}), so the ASN always
 * knows what arrived and moves through its statuses by itself.
 */
final class Asn extends AggregateRoot
{
    private AsnStatus $status = AsnStatus::Expected;

    private ?ReceiptId $openReceiptId = null;

    /**
     * @param list<AsnLine> $lines
     */
    private function __construct(
        private readonly AsnId $id,
        private readonly TenantId $tenantId,
        private readonly AsnReference $reference,
        private readonly SupplierName $supplier,
        private readonly \DateTimeImmutable $expectedDate,
        private array $lines,
    ) {
    }

    /**
     * @param list<ExpectedLine> $lines
     *
     * @throws InvalidAsnLines         INB-15
     * @throws ProductNotReceivable    INB-01
     * @throws InvalidExpectedQuantity INB-02
     * @throws AsnReferenceAlreadyUsed INB-03
     */
    public static function register(
        AsnId $id,
        TenantId $tenantId,
        AsnReference $reference,
        SupplierName $supplier,
        \DateTimeImmutable $expectedDate,
        array $lines,
        AsnReferenceRegistry $registry,
        \DateTimeImmutable $at,
    ): self {
        $asnLines = self::linesFrom($lines);

        if ($registry->isReferenceTaken($tenantId, $reference)) {
            throw AsnReferenceAlreadyUsed::forReference($reference);
        }

        $asn = new self($id, $tenantId, $reference, $supplier, $expectedDate->setTime(0, 0), $asnLines);
        $asn->record(new AsnRegistered($id, $reference, $at));

        return $asn;
    }

    /**
     * Starts counting a delivery at a dock. The receipt expects, for each line, the units still
     * to come.
     *
     * @throws AsnNotReceivable   INB-04
     * @throws ReceiptAlreadyOpen INB-16
     */
    public function openReceipt(ReceiptId $receiptId, LocationId $dockLocationId, \DateTimeImmutable $at): Receipt
    {
        if (!$this->status->acceptsReceipts()) {
            throw AsnNotReceivable::inStatus($this->reference, $this->status);
        }
        $this->assertNoOpenReceipt();

        $expected = array_map(
            static fn (AsnLine $line): ExpectedItem => new ExpectedItem($line->productId, $line->remainingUnits()),
            $this->lines,
        );

        $this->openReceiptId = $receiptId;

        return Receipt::open($receiptId, $this->tenantId, $this->id, $dockLocationId, $expected, $at);
    }

    /**
     * Closes the open receipt and adds what it counted to the lines. The ASN is received when
     * every line got at least what it expected, and partially received otherwise.
     *
     * @throws ReceiptAsnMismatch INB-05
     */
    public function closeReceipt(Receipt $receipt, \DateTimeImmutable $at): void
    {
        if (!$receipt->asnId()->equals($this->id) || !$this->openReceiptId instanceof ReceiptId || !$this->openReceiptId->equals($receipt->id())) {
            throw ReceiptAsnMismatch::between($receipt->id(), $this->id);
        }

        $receipt->close($at);

        $this->lines = array_map(
            static fn (AsnLine $line): AsnLine => $line->withReceived($receipt->receivedUnitsOf($line->productId)),
            $this->lines,
        );
        $this->openReceiptId = null;
        $this->status = $this->isFullyReceived() ? AsnStatus::Received : AsnStatus::PartiallyReceived;
    }

    /**
     * @throws ReceiptAlreadyOpen  INB-16
     * @throws AsnStatusNotAllowed
     */
    public function cancel(\DateTimeImmutable $at): void
    {
        $this->assertNoOpenReceipt();

        if (AsnStatus::Expected !== $this->status) {
            throw AsnStatusNotAllowed::toCancel($this->reference, $this->status);
        }

        $this->status = AsnStatus::Cancelled;
        $this->record(new AsnCancelled($this->id, $at));
    }

    /**
     * Ends the ASN once goods have arrived, even if some are still missing (a shortage).
     *
     * @throws ReceiptAlreadyOpen  INB-16
     * @throws AsnStatusNotAllowed
     */
    public function close(\DateTimeImmutable $at): void
    {
        $this->assertNoOpenReceipt();

        if (AsnStatus::PartiallyReceived !== $this->status && AsnStatus::Received !== $this->status) {
            throw AsnStatusNotAllowed::toClose($this->reference, $this->status);
        }

        $closedFrom = $this->status;
        $this->status = AsnStatus::Closed;
        $this->record(new AsnClosed($this->id, $closedFrom, $at));
    }

    public function id(): AsnId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function reference(): AsnReference
    {
        return $this->reference;
    }

    public function supplier(): SupplierName
    {
        return $this->supplier;
    }

    public function expectedDate(): \DateTimeImmutable
    {
        return $this->expectedDate;
    }

    public function status(): AsnStatus
    {
        return $this->status;
    }

    public function openReceiptId(): ?ReceiptId
    {
        return $this->openReceiptId;
    }

    /**
     * @return list<AsnLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @param list<ExpectedLine> $lines
     *
     * @return list<AsnLine>
     */
    private static function linesFrom(array $lines): array
    {
        if ([] === $lines) {
            throw InvalidAsnLines::none(); // INB-15
        }

        $asnLines = [];
        foreach ($lines as $line) {
            $productId = $line->product->id;

            if (!$line->product->available) {
                throw ProductNotReceivable::notAvailable($productId->value); // INB-01
            }
            if ($line->units < 1) {
                throw InvalidExpectedQuantity::forLine($productId, $line->units); // INB-02
            }
            if (isset($asnLines[$productId->value])) {
                throw InvalidAsnLines::repeatedProduct($productId); // INB-15
            }

            $asnLines[$productId->value] = new AsnLine($productId, $line->units, 0, $line->lot, $line->expiryDate);
        }

        return array_values($asnLines);
    }

    private function assertNoOpenReceipt(): void
    {
        if ($this->openReceiptId instanceof ReceiptId) {
            throw ReceiptAlreadyOpen::onAsn($this->reference); // INB-16
        }
    }

    private function isFullyReceived(): bool
    {
        return array_all($this->lines, static fn (AsnLine $line): bool => $line->isCovered());
    }
}
