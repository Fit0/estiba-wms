<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Outbound\Domain\Event\PackingCompleted;
use App\Outbound\Domain\Event\PackingStarted;
use App\Outbound\Domain\Event\ParcelClosed;
use App\Outbound\Domain\Event\ShipmentDispatched;
use App\Outbound\Domain\Exception\InvalidParcelUnits;
use App\Outbound\Domain\Exception\InvalidParcelWeight;
use App\Outbound\Domain\Exception\NothingToPack;
use App\Outbound\Domain\Exception\OrderNotPicked;
use App\Outbound\Domain\Exception\PackedExceedsPicked;
use App\Outbound\Domain\Exception\PackingIncomplete;
use App\Outbound\Domain\Exception\ParcelEmpty;
use App\Outbound\Domain\Exception\ParcelNotFound;
use App\Outbound\Domain\Exception\ProductNotPicked;
use App\Outbound\Domain\Exception\ShipmentAlreadyDispatched;
use App\Outbound\Domain\Exception\ShipmentAlreadyPacked;
use App\Outbound\Domain\Exception\ShipmentNotPacked;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;

/**
 * The picked units of one order, put into parcels. It remembers what was picked when packing began,
 * and every parcel it closes takes a part of that.
 *
 * A parcel exists in the system only closed, with its SSCC: the packer fills a physical box and
 * tells the system what is inside when it is sealed (OUT-19, OUT-21). When it is packed, a supervisor
 * dispatches it to a carrier (OUT-25).
 */
final class Shipment extends AggregateRoot
{
    /** The heaviest parcel a carrier takes, in grams: a tonne is a pallet, not a parcel. */
    public const int MAX_PARCEL_WEIGHT_GRAMS = 1_000_000;

    private ShipmentStatus $status = ShipmentStatus::Packing;

    private ?\DateTimeImmutable $packedAt = null;

    private ?string $carrier = null;

    private ?string $trackingNumber = null;

    private ?\DateTimeImmutable $dispatchedAt = null;

    /** @var list<Parcel> */
    private array $parcels = [];

    /**
     * @param list<ParcelContent> $picked what the order had picked, by product
     */
    private function __construct(
        private readonly ShipmentId $id,
        private readonly TenantId $tenantId,
        private readonly OrderId $orderId,
        private readonly WarehouseId $warehouseId,
        private readonly array $picked,
        private readonly \DateTimeImmutable $startedAt,
    ) {
    }

    /**
     * Begins packing a picked order. That there is only one shipment per order is for the caller
     * to check (OUT-20), as it needs the repository.
     *
     * @throws OrderNotPicked when the order is not in the picked status
     * @throws NothingToPack  OUT-20, when every unit was short picked
     */
    public static function start(ShipmentId $id, TenantId $tenantId, Order $order, \DateTimeImmutable $at): self
    {
        if (OrderStatus::Picked !== $order->status()) {
            throw OrderNotPicked::forOrder($order->reference(), $order->status()->value);
        }

        $picked = [];
        foreach ($order->lines() as $line) {
            if ($line->pickedUnits > 0) {
                $picked = self::merged($picked, new ParcelContent($line->productId, $line->pickedUnits));
            }
        }
        if ([] === $picked) {
            throw NothingToPack::forOrder($order->reference());
        }

        $shipment = new self($id, $tenantId, $order->id(), $order->warehouseId(), $picked, $at);
        $shipment->record(new PackingStarted($id, $tenantId, $order->id(), $at));

        return $shipment;
    }

    /**
     * Seals a parcel with what is inside, its weight and the SSCC that identifies it.
     *
     * @param list<ParcelContent> $contents
     *
     * @throws ShipmentAlreadyPacked OUT-24
     * @throws ParcelEmpty           OUT-21
     * @throws InvalidParcelWeight   OUT-21
     * @throws ProductNotPicked      OUT-22
     * @throws PackedExceedsPicked   OUT-22
     */
    public function closeParcel(Sscc $sscc, array $contents, int $weightGrams, \DateTimeImmutable $at): void
    {
        $this->assertPacking();

        if ([] === $contents) {
            throw ParcelEmpty::closing();
        }
        if ($weightGrams < 1 || $weightGrams > self::MAX_PARCEL_WEIGHT_GRAMS) {
            throw InvalidParcelWeight::of($weightGrams, self::MAX_PARCEL_WEIGHT_GRAMS);
        }

        $merged = [];
        foreach ($contents as $content) {
            $merged = self::merged($merged, $content);
        }
        foreach ($merged as $content) {
            $this->assertFits($content);
        }

        $this->parcels[] = new Parcel($sscc, $merged, $weightGrams, $at);
        $this->record(new ParcelClosed($this->id, $this->tenantId, $this->orderId, $sscc, $at));
    }

    /**
     * Declares the shipment packed: every picked unit is in a closed parcel (OUT-23).
     *
     * @throws ShipmentAlreadyPacked OUT-24
     * @throws PackingIncomplete     OUT-23
     */
    public function completePacking(\DateTimeImmutable $at): void
    {
        $this->assertPacking();

        $missing = $this->unitsToPack();
        if ($missing > 0) {
            throw PackingIncomplete::missing($missing);
        }

        $this->status = ShipmentStatus::Packed;
        $this->packedAt = $at;
        $this->record(new PackingCompleted($this->id, $this->tenantId, $this->orderId, $at));
    }

    /**
     * The parcels are handed to the carrier and the goods leave the warehouse (OUT-25). Whatever
     * stock and order changes that means is for the caller to make in the same transaction.
     *
     * @throws ShipmentAlreadyDispatched OUT-25
     * @throws ShipmentNotPacked         OUT-25
     */
    public function dispatch(CarrierName $carrier, ?TrackingNumber $trackingNumber, \DateTimeImmutable $at): void
    {
        match ($this->status) {
            ShipmentStatus::Dispatched => throw ShipmentAlreadyDispatched::forId($this->id->value),
            ShipmentStatus::Packing => throw ShipmentNotPacked::forId($this->id->value),
            ShipmentStatus::Packed => null,
        };

        $this->status = ShipmentStatus::Dispatched;
        $this->carrier = $carrier->value;
        $this->trackingNumber = $trackingNumber?->value;
        $this->dispatchedAt = $at;
        $this->record(new ShipmentDispatched($this->id, $this->tenantId, $this->orderId, $carrier->value, $at));
    }

    /**
     * Picked units that are not in a parcel yet.
     */
    public function unitsToPack(): int
    {
        $picked = array_sum(array_map(static fn (ParcelContent $content): int => $content->units, $this->picked));
        $packed = array_sum(array_map(static fn (Parcel $parcel): int => $parcel->totalUnits(), $this->parcels));

        return $picked - $packed;
    }

    /**
     * @throws ParcelNotFound
     */
    public function parcel(Sscc $sscc): Parcel
    {
        foreach ($this->parcels as $parcel) {
            if ($parcel->sscc->equals($sscc)) {
                return $parcel;
            }
        }

        throw ParcelNotFound::forSscc($sscc->value);
    }

    /**
     * The position of a parcel among the ones of the shipment, from 1, in the order they were closed.
     *
     * @throws ParcelNotFound
     */
    public function parcelNumber(Sscc $sscc): int
    {
        foreach ($this->parcels as $index => $parcel) {
            if ($parcel->sscc->equals($sscc)) {
                return $index + 1;
            }
        }

        throw ParcelNotFound::forSscc($sscc->value);
    }

    public function id(): ShipmentId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function warehouseId(): WarehouseId
    {
        return $this->warehouseId;
    }

    public function status(): ShipmentStatus
    {
        return $this->status;
    }

    /**
     * @return list<ParcelContent>
     */
    public function picked(): array
    {
        return $this->picked;
    }

    /**
     * @return list<Parcel>
     */
    public function parcels(): array
    {
        return $this->parcels;
    }

    public function carrier(): ?string
    {
        return $this->carrier;
    }

    public function trackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function dispatchedAt(): ?\DateTimeImmutable
    {
        return $this->dispatchedAt;
    }

    public function startedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function packedAt(): ?\DateTimeImmutable
    {
        return $this->packedAt;
    }

    private function assertPacking(): void
    {
        if (ShipmentStatus::Packing !== $this->status) {
            throw ShipmentAlreadyPacked::forId($this->id->value);
        }
    }

    /**
     * The units of this product in the parcel, added to what is already packed, cannot be more than
     * were picked (OUT-22).
     */
    private function assertFits(ParcelContent $content): void
    {
        $picked = $this->pickedOf($content->productId);
        if (null === $picked) {
            throw ProductNotPicked::forProduct($content->productId->value);
        }

        $packed = $content->units;
        foreach ($this->parcels as $parcel) {
            foreach ($parcel->contents as $inParcel) {
                if ($inParcel->productId->equals($content->productId)) {
                    $packed += $inParcel->units;
                }
            }
        }
        if ($packed > $picked) {
            throw PackedExceedsPicked::forProduct($content->productId->value, $packed, $picked);
        }
    }

    private function pickedOf(ProductId $productId): ?int
    {
        foreach ($this->picked as $content) {
            if ($content->productId->equals($productId)) {
                return $content->units;
            }
        }

        return null;
    }

    /**
     * The list with these units added: to the entry of the same product, or as a new one.
     *
     * @param list<ParcelContent> $contents
     *
     * @return list<ParcelContent>
     *
     * @throws InvalidParcelUnits
     */
    private static function merged(array $contents, ParcelContent $added): array
    {
        foreach ($contents as $index => $existing) {
            if ($existing->productId->equals($added->productId)) {
                $contents[$index] = $existing->plus($added->units);

                return $contents;
            }
        }

        $contents[] = $added;

        return $contents;
    }
}
