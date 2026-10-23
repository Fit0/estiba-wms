<?php

declare(strict_types=1);

namespace App\Tests\Functional\Outbound\Persistence;

use App\Outbound\Domain\LineAllocation;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\OrderStatus;
use App\Outbound\Domain\PickList;
use App\Outbound\Domain\PickListId;
use App\Outbound\Domain\PickListStatus;
use App\Outbound\Domain\Wave;
use App\Outbound\Domain\WaveId;
use App\Outbound\Domain\WaveStatus;
use App\Outbound\Infrastructure\Persistence\Doctrine\DoctrineOrderRepository;
use App\Outbound\Infrastructure\Persistence\Doctrine\DoctrinePickListRepository;
use App\Outbound\Infrastructure\Persistence\Doctrine\DoctrineWaveRepository;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Outbound\Domain\OutboundMother;
use App\Tests\Unit\Support\Ids;

final class DoctrineWaveRepositoryTest extends PersistenceTestCase
{
    private DoctrineWaveRepository $waves;

    private DoctrinePickListRepository $pickLists;

    private DoctrineOrderRepository $orders;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->waves = new DoctrineWaveRepository($this->entityManager);
        $this->pickLists = new DoctrinePickListRepository($this->entityManager);
        $this->orders = new DoctrineOrderRepository($this->entityManager);
    }

    public function testSavesAWaveWithItsOrdersAndItsPickLists(): void
    {
        $order = OutboundMother::order();
        $wave = Wave::plan(WaveId::fromString(Ids::next()), $order->tenantId(), [$order], OutboundMother::at());
        $lists = [PickListId::fromString(Ids::next()), PickListId::fromString(Ids::next())];
        $wave->release($lists, new \DateTimeImmutable('2026-10-24T08:15:00+00:00'));
        $this->waves->save($wave);
        $this->flushAndForget();

        $loaded = $this->waves->find($wave->id());

        self::assertInstanceOf(Wave::class, $loaded);
        self::assertSame(WaveStatus::Released, $loaded->status());
        self::assertTrue($order->warehouseId()->equals($loaded->warehouseId()));
        self::assertSame([$order->id()->value], array_map(static fn (OrderId $id): string => $id->value, $loaded->orderIds()));
        self::assertSame([$lists[0]->value, $lists[1]->value], array_map(static fn (PickListId $id): string => $id->value, $loaded->pickListIds()));
        self::assertEquals(OutboundMother::at(), $loaded->createdAt());
        self::assertEquals(new \DateTimeImmutable('2026-10-24T08:15:00+00:00'), $loaded->releasedAt());
    }

    public function testAPlannedWaveHasNotBeenReleased(): void
    {
        $order = OutboundMother::order();
        $wave = Wave::plan(WaveId::fromString(Ids::next()), $order->tenantId(), [$order], OutboundMother::at());
        $this->waves->save($wave);
        $this->flushAndForget();

        $loaded = $this->waves->find($wave->id());

        self::assertInstanceOf(Wave::class, $loaded);
        self::assertSame(WaveStatus::Planned, $loaded->status());
        self::assertNull($loaded->releasedAt());
        self::assertSame([], $loaded->pickListIds());
    }

    public function testSearchesTheWavesOfATenantByStatusTheLatestFirst(): void
    {
        $tenantId = OutboundMother::tenantId();
        $older = $this->plannedWave($tenantId, '2026-10-20T08:00:00+00:00');
        $newer = $this->plannedWave($tenantId, '2026-10-22T08:00:00+00:00');
        $cancelled = $this->plannedWave($tenantId, '2026-10-21T08:00:00+00:00');
        $cancelled->cancel(OutboundMother::at());
        $this->plannedWave(OutboundMother::tenantId(), '2026-10-23T08:00:00+00:00');
        $this->flushAndForget();

        $all = $this->waves->search($tenantId, null, PageRequest::of(1, 10));
        $onlyCancelled = $this->waves->search($tenantId, WaveStatus::Cancelled, PageRequest::of(1, 10));

        self::assertSame(3, $all->total);
        self::assertSame([$newer->id()->value, $cancelled->id()->value, $older->id()->value], array_map(static fn (Wave $wave): string => $wave->id()->value, $all->items));
        self::assertSame(1, $onlyCancelled->total);
    }

    public function testSavesAPickListWithItsStopsInOrder(): void
    {
        $waveId = WaveId::fromString(Ids::next());
        $tenantId = OutboundMother::tenantId();
        $pickList = PickList::generate(PickListId::fromString(Ids::next()), $tenantId, $waveId, [
            OutboundMother::pickRequest('A-01-01', units: 3, lot: 'L-1', expiryDate: new \DateTimeImmutable('2027-04-30')),
            OutboundMother::pickRequest('B-01-02', units: 5),
        ], OutboundMother::at());
        $this->pickLists->save($pickList);
        $this->flushAndForget();

        $loaded = $this->pickLists->find($pickList->id());

        self::assertInstanceOf(PickList::class, $loaded);
        self::assertSame(PickListStatus::Generated, $loaded->status());
        self::assertSame($waveId->value, $loaded->waveId()->value);
        self::assertSame(8, $loaded->unitsToPick());
        self::assertSame([1, 2], array_map(static fn ($line): int => $line->position, $loaded->lines()));
        self::assertSame(['A-01-01', 'B-01-02'], array_map(static fn ($line): string => $line->locationCode, $loaded->lines()));
        self::assertSame('L-1', $loaded->lines()[0]->lot);
        self::assertSame('2027-04-30', $loaded->lines()[0]->expiryDate?->format('Y-m-d'));
        self::assertNull($loaded->lines()[1]->lot);
        self::assertNull($loaded->lines()[1]->expiryDate);
        self::assertSame(0, $loaded->lines()[0]->quantityPicked);
    }

    public function testFindsThePickListsOfAWaveWithinTheTenant(): void
    {
        $tenantId = OutboundMother::tenantId();
        $wave = WaveId::fromString(Ids::next());
        $mine = $this->pickList($tenantId, $wave);
        $this->pickList($tenantId, WaveId::fromString(Ids::next()));
        $this->pickList(OutboundMother::tenantId(), $wave);
        $this->flushAndForget();

        self::assertSame([$mine->id()->value], array_map(static fn (PickList $list): string => $list->id()->value, $this->pickLists->findOf($tenantId, $wave)));
        self::assertCount(2, $this->pickLists->findOf($tenantId, null));
    }

    public function testACancelledPickListStaysCancelled(): void
    {
        $pickList = $this->pickList(OutboundMother::tenantId(), WaveId::fromString(Ids::next()));
        $this->flushAndForget();

        $loaded = $this->pickLists->find($pickList->id());
        self::assertInstanceOf(PickList::class, $loaded);
        $loaded->cancel();
        $this->flushAndForget();

        $again = $this->pickLists->find($pickList->id());
        self::assertInstanceOf(PickList::class, $again);
        self::assertSame(PickListStatus::Cancelled, $again->status());
    }

    public function testAnOrderRemembersItsWaveAndTheBackorderItsCustomerAccepted(): void
    {
        $order = OutboundMother::order([OutboundMother::item(10)]);
        $order->recordAllocations([new LineAllocation(1, OutboundMother::reservationId(), 4)], OutboundMother::at());
        $order->acceptBackorder();
        $waveId = WaveId::fromString(Ids::next());
        $order->joinWave($waveId);
        $this->orders->save($order);
        $this->flushAndForget();

        $loaded = $this->orders->find($order->id());

        self::assertInstanceOf(Order::class, $loaded);
        self::assertSame(OrderStatus::InWave, $loaded->status());
        self::assertTrue($loaded->backorderAccepted());
        self::assertSame($waveId->value, $loaded->waveId()?->value);
    }

    public function testAnOrderThatIsInNoWaveHasNone(): void
    {
        $order = OutboundMother::order();
        $this->orders->save($order);
        $this->flushAndForget();

        $loaded = $this->orders->find($order->id());

        self::assertInstanceOf(Order::class, $loaded);
        self::assertNull($loaded->waveId());
        self::assertFalse($loaded->backorderAccepted());
    }

    private function plannedWave(TenantId $tenantId, string $at): Wave
    {
        $order = OutboundMother::order(tenantId: $tenantId, reference: 'PED-'.Ids::next());
        $wave = Wave::plan(WaveId::fromString(Ids::next()), $tenantId, [$order], new \DateTimeImmutable($at));
        $this->waves->save($wave);

        return $wave;
    }

    private function pickList(TenantId $tenantId, WaveId $waveId): PickList
    {
        $pickList = PickList::generate(PickListId::fromString(Ids::next()), $tenantId, $waveId, [OutboundMother::pickRequest('A-01-01')], OutboundMother::at());
        $this->pickLists->save($pickList);

        return $pickList;
    }
}
