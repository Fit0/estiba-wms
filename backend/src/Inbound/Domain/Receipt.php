<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

use App\Inbound\Domain\Event\DiscrepancyDetected;
use App\Inbound\Domain\Event\ItemReceived;
use App\Inbound\Domain\Event\OverReceiptApproved;
use App\Inbound\Domain\Event\ReceiptClosed;
use App\Inbound\Domain\Event\ReceiptOpened;
use App\Inbound\Domain\Exception\ExpiryDatePassed;
use App\Inbound\Domain\Exception\InvalidReceivedQuantity;
use App\Inbound\Domain\Exception\OverReceiptApproval;
use App\Inbound\Domain\Exception\ProductNotReceivable;
use App\Inbound\Domain\Exception\ReceiptAlreadyClosed;
use App\Inbound\Domain\Exception\TrackingDataMismatch;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;

/**
 * One receiving session at a dock: what physically arrived against one ASN (INB-05).
 *
 * The receipt copies, when it opens, what it expects of each product. Every count adds a line,
 * and the discrepancies are worked out from the expected items and the lines whenever they are
 * asked for, so they can never disagree with the counts.
 *
 * A receipt is opened and closed through its ASN ({@see Asn::openReceipt()},
 * {@see Asn::closeReceipt()}).
 */
final class Receipt extends AggregateRoot
{
    private ReceiptStatus $status = ReceiptStatus::Open;

    private bool $overReceiptApproved = false;

    /** @var list<ReceivedLine> */
    private array $lines = [];

    private ?\DateTimeImmutable $closedAt = null;

    /**
     * @param list<ExpectedItem> $expected
     */
    private function __construct(
        private readonly ReceiptId $id,
        private readonly TenantId $tenantId,
        private readonly AsnId $asnId,
        private readonly LocationId $dockLocationId,
        private readonly array $expected,
        private readonly \DateTimeImmutable $openedAt,
    ) {
    }

    /**
     * @internal use {@see Asn::openReceipt()}, which checks that the ASN can be received against
     *           (INB-04) and has no other receipt open (INB-16)
     *
     * @param list<ExpectedItem> $expected
     */
    public static function open(ReceiptId $id, TenantId $tenantId, AsnId $asnId, LocationId $dockLocationId, array $expected, \DateTimeImmutable $at): self
    {
        $receipt = new self($id, $tenantId, $asnId, $dockLocationId, $expected, $at);
        $receipt->record(new ReceiptOpened($id, $asnId, $dockLocationId, $at));

        return $receipt;
    }

    /**
     * Records a count at the dock. A product that is not on the ASN is recorded too, as an
     * unexpected product (INB-06), and so is more than was expected (INB-08). Receiving more of a
     * product than expected withdraws an earlier approval of the over-receipt, because the
     * supervisor did not see these units.
     *
     * @throws ReceiptAlreadyClosed    INB-09
     * @throws InvalidReceivedQuantity
     * @throws ProductNotReceivable    INB-01
     * @throws TrackingDataMismatch    INB-07
     * @throws ExpiryDatePassed        INB-07
     */
    public function receiveItem(
        ReceivableProduct $product,
        int $units,
        ?LotNumber $lot,
        ?\DateTimeImmutable $expiryDate,
        ItemCondition $condition,
        \DateTimeImmutable $at,
    ): ReceivedLine {
        $this->assertOpen();
        if ($units < 1) {
            throw InvalidReceivedQuantity::forUnits($units);
        }
        if (!$product->available) {
            throw ProductNotReceivable::notAvailable($product->id->value); // INB-01
        }
        $this->assertTrackingData($product, $lot, $expiryDate, $at);

        $line = new ReceivedLine(\count($this->lines) + 1, $product->id, $units, $lot, $expiryDate, $condition, $at);
        $this->lines[] = $line;
        $this->record(new ItemReceived($this->id, $line->lineNumber, $product->id, $units, $condition, $at));
        $this->recordDiscrepanciesOf($line, $at);

        return $line;
    }

    /**
     * A supervisor accepts that the receipt holds more than the ASN announced (INB-08). Only a
     * supervisor reaches this: the API refuses the call to anyone else.
     *
     * @throws ReceiptAlreadyClosed INB-09
     * @throws OverReceiptApproval  INB-08
     */
    public function approveOverReceipt(\DateTimeImmutable $at): void
    {
        $this->assertOpen();
        if (0 === $this->overReceivedUnits()) {
            throw OverReceiptApproval::notNeeded();
        }
        if ($this->overReceiptApproved) {
            throw OverReceiptApproval::alreadyGiven();
        }

        $this->overReceiptApproved = true;
        $this->record(new OverReceiptApproved($this->id, $at));
    }

    /**
     * @internal use {@see Asn::closeReceipt()}, which adds what arrived to the ASN
     *
     * @throws ReceiptAlreadyClosed INB-09
     * @throws OverReceiptApproval  INB-08
     */
    public function close(\DateTimeImmutable $at): void
    {
        $this->assertOpen();

        $overReceived = $this->overReceivedUnits();
        if ($overReceived > 0 && !$this->overReceiptApproved) {
            throw OverReceiptApproval::required($overReceived);
        }

        $this->status = ReceiptStatus::Closed;
        $this->closedAt = $at;
        $this->record(new ReceiptClosed($this->id, $this->asnId, $at)); // INB-09: once, because a closed receipt cannot close again
    }

    /**
     * The differences between what the receipt expected and what it counted: for each product, a
     * shortage or an excess, an unexpected product, and the damaged units. While the receipt is
     * open, a shortage only means that the goods have not been counted yet.
     *
     * @return list<Discrepancy>
     */
    public function discrepancies(): array
    {
        $received = $this->unitsByProduct();
        $discrepancies = [];

        foreach ($this->expected as $item) {
            $difference = ($received[$item->productId->value] ?? 0) - $item->units;
            if ($difference < 0) {
                $discrepancies[] = new Discrepancy(DiscrepancyType::Shortage, $item->productId, -$difference);
            } elseif ($difference > 0) {
                $discrepancies[] = new Discrepancy(DiscrepancyType::Excess, $item->productId, $difference);
            }
        }

        foreach ($this->unexpectedProducts() as $productId) {
            $discrepancies[] = new Discrepancy(DiscrepancyType::UnexpectedProduct, $productId, $received[$productId->value] ?? 0);
        }

        foreach ($this->damagedUnitsByProduct() as $damaged) {
            $discrepancies[] = $damaged;
        }

        return $discrepancies;
    }

    /**
     * The units over what the ASN announced: excesses and unexpected products (INB-08). The
     * tolerance is zero.
     */
    public function overReceivedUnits(): int
    {
        $units = 0;
        foreach ($this->discrepancies() as $discrepancy) {
            if ($discrepancy->type->isOverReceipt()) {
                $units += $discrepancy->units;
            }
        }

        return $units;
    }

    /**
     * Every unit of the product that was counted, damaged or not.
     */
    public function receivedUnitsOf(ProductId $productId): int
    {
        return $this->unitsByProduct()[$productId->value] ?? 0;
    }

    public function id(): ReceiptId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function asnId(): AsnId
    {
        return $this->asnId;
    }

    public function dockLocationId(): LocationId
    {
        return $this->dockLocationId;
    }

    public function status(): ReceiptStatus
    {
        return $this->status;
    }

    public function isOverReceiptApproved(): bool
    {
        return $this->overReceiptApproved;
    }

    /**
     * @return list<ExpectedItem>
     */
    public function expected(): array
    {
        return $this->expected;
    }

    /**
     * @return list<ReceivedLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function openedAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    /**
     * INB-07: lot and expiry date are given exactly when the product tracks them, and the goods
     * have not expired.
     */
    private function assertTrackingData(ReceivableProduct $product, ?LotNumber $lot, ?\DateTimeImmutable $expiryDate, \DateTimeImmutable $at): void
    {
        if ($product->trackedByLot && !$lot instanceof LotNumber) {
            throw TrackingDataMismatch::lotRequired($product->id);
        }
        if (!$product->trackedByLot && $lot instanceof LotNumber) {
            throw TrackingDataMismatch::lotNotTracked($product->id);
        }
        if ($product->trackedByExpiry && !$expiryDate instanceof \DateTimeImmutable) {
            throw TrackingDataMismatch::expiryDateRequired($product->id);
        }
        if (!$product->trackedByExpiry && $expiryDate instanceof \DateTimeImmutable) {
            throw TrackingDataMismatch::expiryDateNotTracked($product->id);
        }
        if ($expiryDate instanceof \DateTimeImmutable && $expiryDate->setTime(0, 0) < $at->setTime(0, 0)) {
            throw ExpiryDatePassed::on($expiryDate);
        }
    }

    private function assertOpen(): void
    {
        if (ReceiptStatus::Open !== $this->status) {
            throw ReceiptAlreadyClosed::forId($this->id); // INB-09
        }
    }

    /**
     * Raises what the new line reveals at once. A shortage is only known when the receipt closes.
     */
    private function recordDiscrepanciesOf(ReceivedLine $line, \DateTimeImmutable $at): void
    {
        $expected = $this->expectedUnitsOf($line->productId);

        if (null === $expected) {
            $this->overReceiptApproved = false;
            $this->record(new DiscrepancyDetected($this->id, $line->productId, DiscrepancyType::UnexpectedProduct, $at));
        } elseif ($this->receivedUnitsOf($line->productId) > $expected) {
            $this->overReceiptApproved = false;
            $this->record(new DiscrepancyDetected($this->id, $line->productId, DiscrepancyType::Excess, $at));
        }

        if (ItemCondition::Damaged === $line->condition) {
            $this->record(new DiscrepancyDetected($this->id, $line->productId, DiscrepancyType::Damaged, $at));
        }
    }

    private function expectedUnitsOf(ProductId $productId): ?int
    {
        foreach ($this->expected as $item) {
            if ($item->productId->equals($productId)) {
                return $item->units;
            }
        }

        return null;
    }

    /**
     * @return array<string, int> the units counted of each product, by product identifier
     */
    private function unitsByProduct(): array
    {
        $units = [];
        foreach ($this->lines as $line) {
            $units[$line->productId->value] = ($units[$line->productId->value] ?? 0) + $line->units;
        }

        return $units;
    }

    /**
     * @return list<ProductId> the products counted that the receipt did not expect, in the order they were first counted
     */
    private function unexpectedProducts(): array
    {
        $products = [];
        foreach ($this->lines as $line) {
            if (null === $this->expectedUnitsOf($line->productId)) {
                $products[$line->productId->value] = $line->productId;
            }
        }

        return array_values($products);
    }

    /**
     * @return list<Discrepancy>
     */
    private function damagedUnitsByProduct(): array
    {
        $damaged = [];
        foreach ($this->lines as $line) {
            if (ItemCondition::Damaged !== $line->condition) {
                continue;
            }

            $units = ($damaged[$line->productId->value]->units ?? 0) + $line->units;
            $damaged[$line->productId->value] = new Discrepancy(DiscrepancyType::Damaged, $line->productId, $units);
        }

        return array_values($damaged);
    }
}
