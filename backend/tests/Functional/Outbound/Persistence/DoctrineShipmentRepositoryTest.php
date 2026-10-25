<?php

declare(strict_types=1);

namespace App\Tests\Functional\Outbound\Persistence;

use App\Outbound\Domain\Order;
use App\Outbound\Domain\ParcelContent;
use App\Outbound\Domain\Shipment;
use App\Outbound\Domain\ShipmentId;
use App\Outbound\Domain\ShipmentStatus;
use App\Outbound\Domain\Sscc;
use App\Outbound\Infrastructure\Persistence\Doctrine\DoctrineShipmentRepository;
use App\Outbound\Infrastructure\Persistence\Sscc\DbalSsccSequence;
use App\Shared\Domain\PageRequest;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Outbound\Domain\OutboundMother;
use App\Tests\Unit\Support\Ids;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineShipmentRepositoryTest extends PersistenceTestCase
{
    private DoctrineShipmentRepository $shipments;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->shipments = new DoctrineShipmentRepository($this->entityManager);
    }

    public function testSavesAShipmentWithWhatWasPickedAndItsParcels(): void
    {
        $order = OutboundMother::pickedOrder([10, 4]);
        $shipment = $this->start($order);
        [$first, $second] = $shipment->picked();
        $closedAt = new \DateTimeImmutable('2026-10-24T11:30:15+00:00');
        $shipment->closeParcel(Sscc::compose(0, '8400000', 1), [new ParcelContent($first->productId, 6), new ParcelContent($second->productId, 1)], 2300, $closedAt);
        $this->shipments->save($shipment);
        $this->flushAndForget();

        $loaded = $this->shipments->find($shipment->id());

        self::assertInstanceOf(Shipment::class, $loaded);
        self::assertSame(ShipmentStatus::Packing, $loaded->status());
        self::assertSame($order->id()->value, $loaded->orderId()->value);
        self::assertTrue($order->warehouseId()->equals($loaded->warehouseId()));
        self::assertSame([10, 4], array_map(static fn (ParcelContent $content): int => $content->units, $loaded->picked()));
        self::assertSame(7, $loaded->unitsToPack());
        self::assertCount(1, $loaded->parcels());
        $parcel = $loaded->parcels()[0];
        self::assertSame('084000000000000017', $parcel->sscc->value);
        self::assertSame(2300, $parcel->weightGrams);
        self::assertEquals($closedAt, $parcel->closedAt);
        self::assertSame([6, 1], array_map(static fn (ParcelContent $content): int => $content->units, $parcel->contents));
        self::assertSame($first->productId->value, $parcel->contents[0]->productId->value);
        self::assertNull($loaded->packedAt());
    }

    public function testASavedPackedShipmentIsLoadedPacked(): void
    {
        $shipment = $this->start(OutboundMother::pickedOrder([5]));
        $shipment->closeParcel(Sscc::compose(0, '8400000', 1), $shipment->picked(), 1000, OutboundMother::at());
        $shipment->completePacking(new \DateTimeImmutable('2026-10-24T12:00:00+00:00'));
        $this->shipments->save($shipment);
        $this->flushAndForget();

        $loaded = $this->shipments->find($shipment->id());

        self::assertInstanceOf(Shipment::class, $loaded);
        self::assertSame(ShipmentStatus::Packed, $loaded->status());
        self::assertEquals(new \DateTimeImmutable('2026-10-24T12:00:00+00:00'), $loaded->packedAt());
    }

    public function testFindsTheShipmentOfAnOrder(): void
    {
        $order = OutboundMother::pickedOrder([5]);
        $shipment = $this->start($order);
        $this->shipments->save($shipment);
        $this->flushAndForget();

        self::assertSame($shipment->id()->value, $this->shipments->findByOrder($order->id())?->id()->value);
        self::assertNotInstanceOf(Shipment::class, $this->shipments->findByOrder(OutboundMother::pickedOrder([5])->id()));
    }

    // OUT-20: the unique index holds even when two packers start at once and both pass the check
    public function testTwoShipmentsOfTheSameOrderAreNotAllowed(): void
    {
        $order = OutboundMother::pickedOrder([5]);
        $this->shipments->save($this->start($order));
        $this->shipments->save($this->start($order));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testSearchesATenantsShipmentsLatestFirstAndByStatus(): void
    {
        $tenant = OutboundMother::tenantId();
        $older = $this->start(OutboundMother::pickedOrder([5], tenantId: $tenant), new \DateTimeImmutable('2026-10-24T08:00:00+00:00'));
        $newer = $this->start(OutboundMother::pickedOrder([5], tenantId: $tenant), new \DateTimeImmutable('2026-10-24T09:00:00+00:00'));
        $newer->closeParcel(Sscc::compose(0, '8400000', 1), $newer->picked(), 1000, OutboundMother::at());
        $newer->completePacking(OutboundMother::at());
        $other = $this->start(OutboundMother::pickedOrder([5], tenantId: OutboundMother::tenantId()));
        foreach ([$older, $newer, $other] as $shipment) {
            $this->shipments->save($shipment);
        }
        $this->flushAndForget();

        $all = $this->shipments->search($tenant, null, PageRequest::of(1, 10));
        $packed = $this->shipments->search($tenant, ShipmentStatus::Packed, PageRequest::of(1, 10));

        self::assertSame(2, $all->total);
        self::assertSame([$newer->id()->value, $older->id()->value], array_map(static fn (Shipment $shipment): string => $shipment->id()->value, $all->items));
        self::assertSame([$newer->id()->value], array_map(static fn (Shipment $shipment): string => $shipment->id()->value, $packed->items));
    }

    public function testTheSequenceCountsUpFromOneForEachCompanyPrefix(): void
    {
        $connection = $this->entityManager->getConnection();
        $ours = new DbalSsccSequence($connection, '8400000');
        $theirs = new DbalSsccSequence($connection, '8411111');

        self::assertSame('084000000000000017', $ours->next()->value);
        self::assertSame('084000000000000024', $ours->next()->value);
        self::assertSame('084111110000000018', $theirs->next()->value);
        self::assertSame('084000000000000031', $ours->next()->value);
    }

    public function testTheExtensionDigitIsPartOfTheCode(): void
    {
        $sequence = new DbalSsccSequence($this->entityManager->getConnection(), '8400000', 3);

        self::assertSame('384000000000000018', $sequence->next()->value);
    }

    public function testASerialGoesBackWhenTheTransactionIsRolledBack(): void
    {
        $connection = $this->entityManager->getConnection();
        $sequence = new DbalSsccSequence($connection, '8400000');

        $connection->beginTransaction();
        $sequence->next();
        $connection->rollBack();

        self::assertSame('084000000000000017', $sequence->next()->value);
    }

    private function start(Order $order, ?\DateTimeImmutable $at = null): Shipment
    {
        return Shipment::start(ShipmentId::fromString(Ids::next()), $order->tenantId(), $order, $at ?? OutboundMother::at());
    }
}
