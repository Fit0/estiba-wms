<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\Event\PackingCompleted;
use App\Outbound\Domain\Event\PackingStarted;
use App\Outbound\Domain\Event\ParcelClosed;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\Parcel;
use App\Outbound\Domain\ParcelContent;
use App\Outbound\Domain\ProductId;
use App\Outbound\Domain\Shipment;
use App\Outbound\Domain\ShipmentId;
use App\Outbound\Domain\ShipmentStatus;
use App\Outbound\Domain\Sscc;
use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

/**
 * Putting the picked units of an order into parcels (OUT-19 to OUT-24).
 */
final class ShipmentTest extends TestCase
{
    use AssertsDomainErrors;

    public function testPackingStartsFromWhatWasPicked(): void
    {
        $order = OutboundMother::pickedOrder([10, 4]);

        $shipment = $this->start($order);

        self::assertSame(ShipmentStatus::Packing, $shipment->status());
        self::assertSame([10, 4], array_map(static fn (ParcelContent $content): int => $content->units, $shipment->picked()));
        self::assertSame(14, $shipment->unitsToPack());
        self::assertSame($order->id()->value, $shipment->orderId()->value);
        self::assertSame($order->warehouseId()->value, $shipment->warehouseId()->value);
        $events = $shipment->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PackingStarted::class, $events[0]);
    }

    public function testWhatWasShortPickedIsNotToBePacked(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10, 4], [1 => 3]));

        self::assertSame([7, 4], array_map(static fn (ParcelContent $content): int => $content->units, $shipment->picked()));
        self::assertSame(11, $shipment->unitsToPack());
    }

    public function testALineThatWasShortPickedInFullIsLeftOut(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10, 4], [2 => 4]));

        self::assertCount(1, $shipment->picked());
        self::assertSame(10, $shipment->unitsToPack());
    }

    // OUT-20
    public function testNothingPickedMeansNothingToPack(): void
    {
        $order = OutboundMother::pickedOrder([5], [1 => 5]);

        self::assertDomainError('outbound.nothing_to_pack', ['reference' => 'PED-2027-0001'], fn (): Shipment => $this->start($order));
    }

    // OUT-20
    public function testOnlyAPickedOrderCanBePacked(): void
    {
        $order = OutboundMother::orderInWave([5]);

        self::assertDomainError('outbound.order_not_picked', ['reference' => 'PED-2027-0001', 'status' => 'in_wave'], fn (): Shipment => $this->start($order));
    }

    // OUT-19
    public function testClosingAParcelKeepsItsSsccContentsAndWeight(): void
    {
        $order = OutboundMother::pickedOrder([10, 4]);
        $shipment = $this->start($order);
        $shipment->pullEvents();
        $sscc = Sscc::compose(0, '8400000', 1);
        $first = $shipment->picked()[0]->productId;

        $shipment->closeParcel($sscc, [new ParcelContent($first, 6)], 2300, OutboundMother::at());

        $parcel = $shipment->parcel($sscc);
        self::assertSame($sscc->value, $parcel->sscc->value);
        self::assertSame(6, $parcel->totalUnits());
        self::assertSame(2300, $parcel->weightGrams);
        self::assertEquals(OutboundMother::at(), $parcel->closedAt);
        self::assertSame(8, $shipment->unitsToPack());
        self::assertSame(ShipmentStatus::Packing, $shipment->status());
        $events = $shipment->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ParcelClosed::class, $events[0]);
        self::assertSame($sscc->value, $events[0]->sscc->value);
    }

    public function testTheSameProductTwiceInAParcelIsAddedUp(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10]));
        $product = $shipment->picked()[0]->productId;

        $shipment->closeParcel(Sscc::compose(0, '8400000', 1), [new ParcelContent($product, 3), new ParcelContent($product, 2)], 900, OutboundMother::at());

        $contents = $shipment->parcels()[0]->contents;
        self::assertCount(1, $contents);
        self::assertSame(5, $contents[0]->units);
    }

    public function testParcelsAreNumberedInTheOrderTheyAreClosed(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10]));
        $product = $shipment->picked()[0]->productId;
        $one = Sscc::compose(0, '8400000', 1);
        $two = Sscc::compose(0, '8400000', 2);

        $shipment->closeParcel($one, [new ParcelContent($product, 4)], 900, OutboundMother::at());
        $shipment->closeParcel($two, [new ParcelContent($product, 6)], 1200, OutboundMother::at());

        self::assertSame(1, $shipment->parcelNumber($one));
        self::assertSame(2, $shipment->parcelNumber($two));
    }

    public function testAskingForAParcelItDoesNotHaveIsAnError(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10]));
        $unknown = Sscc::compose(0, '8400000', 9);

        self::assertDomainError('outbound.parcel_not_found', ['sscc' => $unknown->value], static fn (): Parcel => $shipment->parcel($unknown));
        self::assertDomainError('outbound.parcel_not_found', ['sscc' => $unknown->value], static fn (): int => $shipment->parcelNumber($unknown));
    }

    // OUT-21
    public function testAParcelCannotBeEmpty(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10]));

        self::assertDomainError('outbound.parcel_empty', [], static fn () => $shipment->closeParcel(Sscc::compose(0, '8400000', 1), [], 900, OutboundMother::at()));
    }

    // OUT-21
    public function testAParcelHasToWeighSomething(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10]));
        $content = [new ParcelContent($shipment->picked()[0]->productId, 1)];

        foreach ([0, -5, Shipment::MAX_PARCEL_WEIGHT_GRAMS + 1] as $grams) {
            self::assertDomainError(
                'outbound.parcel_weight_invalid',
                ['grams' => $grams, 'max' => Shipment::MAX_PARCEL_WEIGHT_GRAMS],
                static fn () => $shipment->closeParcel(Sscc::compose(0, '8400000', 1), $content, $grams, OutboundMother::at()),
            );
        }
        $shipment->closeParcel(Sscc::compose(0, '8400000', 1), $content, Shipment::MAX_PARCEL_WEIGHT_GRAMS, OutboundMother::at());
        self::assertCount(1, $shipment->parcels());
    }

    // OUT-21
    public function testAParcelHoldsAPositiveNumberOfUnits(): void
    {
        $product = ProductId::fromString(Ids::next());

        foreach ([0, -1] as $units) {
            self::assertDomainError('outbound.parcel_units_invalid', ['productId' => $product->value, 'units' => $units], static fn (): ParcelContent => new ParcelContent($product, $units));
        }
    }

    // OUT-22
    public function testAParcelCannotHoldAProductThatWasNotPicked(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10]));
        $other = ProductId::fromString(Ids::next());

        self::assertDomainError('outbound.product_not_picked', ['productId' => $other->value], static fn () => $shipment->closeParcel(Sscc::compose(0, '8400000', 1), [new ParcelContent($other, 1)], 900, OutboundMother::at()));
        self::assertSame([], $shipment->parcels());
    }

    // OUT-22
    public function testNoMoreIsPackedThanWasPicked(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10]));
        $product = $shipment->picked()[0]->productId;

        self::assertDomainError('outbound.packed_exceeds_picked', ['productId' => $product->value, 'packed' => 11, 'picked' => 10], static fn () => $shipment->closeParcel(Sscc::compose(0, '8400000', 1), [new ParcelContent($product, 11)], 900, OutboundMother::at()));
        self::assertSame(10, $shipment->unitsToPack());
    }

    // OUT-22
    public function testWhatIsAlreadyInOtherParcelsCountsAgainstWhatWasPicked(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10]));
        $product = $shipment->picked()[0]->productId;
        $shipment->closeParcel(Sscc::compose(0, '8400000', 1), [new ParcelContent($product, 7)], 900, OutboundMother::at());

        self::assertDomainError('outbound.packed_exceeds_picked', ['productId' => $product->value, 'packed' => 11, 'picked' => 10], static fn () => $shipment->closeParcel(Sscc::compose(0, '8400000', 2), [new ParcelContent($product, 4)], 900, OutboundMother::at()));
        self::assertCount(1, $shipment->parcels());
        $shipment->closeParcel(Sscc::compose(0, '8400000', 2), [new ParcelContent($product, 3)], 700, OutboundMother::at());
        self::assertSame(0, $shipment->unitsToPack());
    }

    // OUT-23
    public function testPackingIsCompleteOnlyWhenEveryPickedUnitIsInAParcel(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10]));
        $product = $shipment->picked()[0]->productId;
        $shipment->closeParcel(Sscc::compose(0, '8400000', 1), [new ParcelContent($product, 6)], 900, OutboundMother::at());

        self::assertDomainError('outbound.packing_incomplete', ['units' => 4], static fn () => $shipment->completePacking(OutboundMother::at()));
        self::assertSame(ShipmentStatus::Packing, $shipment->status());
    }

    // OUT-23
    public function testPackingCannotBeCompletedWithNoParcel(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10]));

        self::assertDomainError('outbound.packing_incomplete', ['units' => 10], static fn () => $shipment->completePacking(OutboundMother::at()));
    }

    public function testCompletingPackingMarksTheShipmentPacked(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10, 4]));
        $this->packEverything($shipment);
        $shipment->pullEvents();

        $shipment->completePacking(OutboundMother::at());

        self::assertSame(ShipmentStatus::Packed, $shipment->status());
        self::assertEquals(OutboundMother::at(), $shipment->packedAt());
        $events = $shipment->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PackingCompleted::class, $events[0]);
    }

    // OUT-24
    public function testAPackedShipmentCannotChange(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([10]));
        $this->packEverything($shipment);
        $shipment->completePacking(OutboundMother::at());
        $product = $shipment->picked()[0]->productId;

        self::assertDomainError('outbound.shipment_already_packed', ['id' => $shipment->id()->value], static fn () => $shipment->closeParcel(Sscc::compose(0, '8400000', 9), [new ParcelContent($product, 1)], 900, OutboundMother::at()));
        self::assertDomainError('outbound.shipment_already_packed', ['id' => $shipment->id()->value], static fn () => $shipment->completePacking(OutboundMother::at()));
    }

    private function start(Order $order): Shipment
    {
        return Shipment::start(ShipmentId::fromString(Ids::next()), $order->tenantId(), $order, OutboundMother::at());
    }

    /**
     * One parcel per product with everything that was picked of it.
     */
    private function packEverything(Shipment $shipment): void
    {
        foreach ($shipment->picked() as $index => $content) {
            $shipment->closeParcel(Sscc::compose(0, '8400000', $index + 1), [$content], 1000, OutboundMother::at());
        }
    }
}
