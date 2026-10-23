<?php

declare(strict_types=1);

namespace App\Tests\Functional\Outbound\Persistence;

use App\Outbound\Domain\LineAllocation;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderPriority;
use App\Outbound\Domain\OrderReference;
use App\Outbound\Domain\OrderStatus;
use App\Outbound\Infrastructure\Persistence\Doctrine\DoctrineOrderRepository;
use App\Shared\Domain\PageRequest;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Outbound\Domain\OutboundMother;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineOrderRepositoryTest extends PersistenceTestCase
{
    private DoctrineOrderRepository $orders;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = new DoctrineOrderRepository($this->entityManager);
    }

    public function testSavesAnOrderWithItsLinesAndAddress(): void
    {
        $order = OutboundMother::order([OutboundMother::item(10), OutboundMother::item(4)]);
        $reservationId = OutboundMother::reservationId();
        $order->recordAllocations([new LineAllocation(1, $reservationId, 6)], OutboundMother::at());
        $this->orders->save($order);
        $this->flushAndForget();

        $loaded = $this->orders->find($order->id());

        self::assertInstanceOf(Order::class, $loaded);
        self::assertSame('PED-2027-0001', $loaded->reference()->value);
        self::assertTrue($order->warehouseId()->equals($loaded->warehouseId()));
        self::assertSame('Marea Moda Zaragoza', $loaded->customer()->value);
        self::assertSame('Calle Mayor 12', $loaded->shipTo()->street);
        self::assertSame('50001', $loaded->shipTo()->postalCode);
        self::assertSame('Zaragoza', $loaded->shipTo()->city);
        self::assertSame('ES', $loaded->shipTo()->countryCode);
        self::assertSame(OrderPriority::Normal, $loaded->priority());
        self::assertSame(OrderStatus::PartiallyAllocated, $loaded->status());
        self::assertEquals($order->cutOffAt(), $loaded->cutOffAt());
        self::assertEquals(OutboundMother::at(), $loaded->registeredAt());
        self::assertCount(2, $loaded->lines());
        self::assertSame(10, $loaded->lines()[0]->orderedUnits);
        self::assertSame(6, $loaded->lines()[0]->allocatedUnits);
        self::assertSame($reservationId->value, $loaded->lines()[0]->reservationId?->value);
        self::assertNull($loaded->lines()[1]->reservationId);
        self::assertSame(8, $loaded->shortageUnits());
    }

    // OUT-01
    public function testTellsWhetherAReferenceIsTakenWithinTheTenant(): void
    {
        $order = OutboundMother::order();
        $this->orders->save($order);
        $this->flushAndForget();

        self::assertTrue($this->orders->isReferenceTaken($order->tenantId(), OrderReference::fromString('PED-2027-0001')));
        self::assertFalse($this->orders->isReferenceTaken($order->tenantId(), OrderReference::fromString('PED-2027-0002')));
        self::assertFalse($this->orders->isReferenceTaken(OutboundMother::tenantId(), OrderReference::fromString('PED-2027-0001')));
    }

    // OUT-01: the database backs the registry against two requests racing
    public function testTheDatabaseRefusesTwoOrdersWithTheSameReference(): void
    {
        $tenantId = OutboundMother::tenantId();
        $this->orders->save(OutboundMother::order(tenantId: $tenantId));
        $this->orders->save(OutboundMother::order(tenantId: $tenantId));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testSearchesByStatusWithinTheTenantMostUrgentFirst(): void
    {
        $tenantId = OutboundMother::tenantId();
        $this->orders->save(OutboundMother::order(tenantId: $tenantId, reference: 'A'));
        $cancelled = OutboundMother::order(tenantId: $tenantId, reference: 'B');
        $cancelled->cancel(OutboundMother::at());
        $this->orders->save($cancelled);
        $this->orders->save(OutboundMother::order(tenantId: OutboundMother::tenantId(), reference: 'C'));
        $this->flushAndForget();

        $all = $this->orders->search($tenantId, null, PageRequest::of(1, 10));
        $onlyCancelled = $this->orders->search($tenantId, OrderStatus::Cancelled, PageRequest::of(1, 10));

        self::assertSame(2, $all->total);
        self::assertSame(['A', 'B'], array_map(static fn (Order $order): string => $order->reference()->value, $all->items));
        self::assertSame(1, $onlyCancelled->total);
        self::assertSame('B', $onlyCancelled->items[0]->reference()->value);
    }
}
