<?php

declare(strict_types=1);

namespace App\Tests\Unit\Warehouse\Domain;

use App\Shared\Domain\StorageClass;
use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Warehouse\Domain\DisplayName;
use App\Warehouse\Domain\Event\LocationCreated;
use App\Warehouse\Domain\Event\WarehouseCreated;
use App\Warehouse\Domain\Event\ZoneDefined;
use App\Warehouse\Domain\Location;
use App\Warehouse\Domain\LocationCapacity;
use App\Warehouse\Domain\LocationCode;
use App\Warehouse\Domain\LocationStatus;
use App\Warehouse\Domain\LocationType;
use App\Warehouse\Domain\PickSequence;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseCode;
use App\Warehouse\Domain\ZoneCode;
use PHPUnit\Framework\TestCase;

final class WarehouseTest extends TestCase
{
    use AssertsDomainErrors;

    public function testCreatesAWarehouseAndRaisesAnEvent(): void
    {
        $tenantId = WarehouseMother::tenantId();
        $id = WarehouseMother::warehouseId();

        $warehouse = Warehouse::create(
            $id,
            $tenantId,
            WarehouseCode::fromString('mad1'),
            DisplayName::fromString('Madrid central warehouse'),
            new InMemoryWarehouseCodes(),
            WarehouseMother::at(),
        );

        self::assertTrue($warehouse->id()->equals($id));
        self::assertTrue($warehouse->tenantId()->equals($tenantId));
        self::assertSame('MAD1', $warehouse->code()->value);
        self::assertSame('Madrid central warehouse', $warehouse->name()->value);
        self::assertSame([], $warehouse->zones());

        $events = $warehouse->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(WarehouseCreated::class, $events[0]);
        self::assertSame('MAD1', $events[0]->code->value);
        self::assertEquals(WarehouseMother::at(), $events[0]->occurredAt());
    }

    public function testPullEventsEmptiesThePendingEvents(): void
    {
        $warehouse = WarehouseMother::warehouse();

        self::assertCount(1, $warehouse->pullEvents());
        self::assertSame([], $warehouse->pullEvents());
    }

    // WH-01: the warehouse code is unique per tenant.
    public function testRejectsACodeAlreadyUsedByTheSameTenant(): void
    {
        $tenantId = WarehouseMother::tenantId();
        $registry = new InMemoryWarehouseCodes()->take($tenantId, WarehouseCode::fromString('MAD1'));

        self::assertDomainError(
            'warehouse.warehouse_code_already_used',
            ['code' => 'MAD1'],
            static fn (): Warehouse => Warehouse::create(
                WarehouseMother::warehouseId(),
                $tenantId,
                WarehouseCode::fromString('mad1'),
                DisplayName::fromString('Another one'),
                $registry,
                WarehouseMother::at(),
            ),
        );
    }

    // WH-01: uniqueness is per tenant, so another tenant can use the same code.
    public function testAnotherTenantCanUseTheSameCode(): void
    {
        $registry = new InMemoryWarehouseCodes()->take(WarehouseMother::tenantId(), WarehouseCode::fromString('MAD1'));

        $warehouse = Warehouse::create(
            WarehouseMother::warehouseId(),
            WarehouseMother::tenantId(),
            WarehouseCode::fromString('MAD1'),
            DisplayName::fromString('Same code, other tenant'),
            $registry,
            WarehouseMother::at(),
        );

        self::assertSame('MAD1', $warehouse->code()->value);
    }

    public function testAddsAZoneAndRaisesAnEvent(): void
    {
        $warehouse = WarehouseMother::warehouse();
        $warehouse->pullEvents();
        $zoneId = WarehouseMother::zoneId();

        $warehouse->addZone(
            $zoneId,
            ZoneCode::fromString('chill'),
            DisplayName::fromString('Chilled goods'),
            StorageClass::Chilled,
            PickSequence::of(2),
            WarehouseMother::at(),
        );

        self::assertCount(1, $warehouse->zones());
        $zone = $warehouse->zones()[0];
        self::assertTrue($zone->id->equals($zoneId));
        self::assertSame('CHILL', $zone->code->value);
        self::assertSame(StorageClass::Chilled, $zone->storageClass);
        self::assertSame(2, $zone->pickSequence->value);

        $events = $warehouse->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ZoneDefined::class, $events[0]);
        self::assertSame(StorageClass::Chilled, $events[0]->storageClass);
    }

    // WH-02: a zone code is unique within its warehouse, ignoring case.
    public function testRejectsAZoneCodeAlreadyUsedInTheWarehouse(): void
    {
        $warehouse = WarehouseMother::warehouse();
        WarehouseMother::addZone($warehouse, WarehouseMother::zoneId(), 'FAST');

        self::assertDomainError(
            'warehouse.zone_code_already_used',
            ['code' => 'FAST'],
            static fn () => WarehouseMother::addZone($warehouse, WarehouseMother::zoneId(), 'fast'),
        );
        self::assertCount(1, $warehouse->zones());
    }

    // WH-02: uniqueness is within one warehouse, so another warehouse can use the same code.
    public function testAnotherWarehouseCanUseTheSameZoneCode(): void
    {
        $first = WarehouseMother::warehouse(code: 'MAD1');
        $second = WarehouseMother::warehouse(code: 'MAD2');

        WarehouseMother::addZone($first, WarehouseMother::zoneId(), 'FAST');
        WarehouseMother::addZone($second, WarehouseMother::zoneId(), 'FAST');

        self::assertCount(1, $second->zones());
    }

    public function testDefinesALocationInOneOfItsZones(): void
    {
        $warehouse = WarehouseMother::warehouse();
        $zoneId = WarehouseMother::zoneId();
        WarehouseMother::addZone($warehouse, $zoneId);
        $locationId = WarehouseMother::locationId();

        $location = $warehouse->defineLocation(
            $locationId,
            $zoneId,
            LocationCode::fromString('a-03-02'),
            LocationType::PickFace,
            LocationCapacity::unlimited(),
            PickSequence::of(7),
            new InMemoryLocationCodes(),
            WarehouseMother::at(),
        );

        self::assertTrue($location->id()->equals($locationId));
        self::assertTrue($location->warehouseId()->equals($warehouse->id()));
        self::assertTrue($location->tenantId()->equals($warehouse->tenantId()));
        self::assertTrue($location->zoneId()->equals($zoneId));
        self::assertSame('A-03-02', $location->code()->value);
        self::assertSame(LocationType::PickFace, $location->type());
        self::assertSame(7, $location->pickSequence()->value);
        self::assertSame(LocationStatus::Active, $location->status());

        $events = $location->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LocationCreated::class, $events[0]);
        self::assertSame('A-03-02', $events[0]->code->value);
    }

    // WH-05: a location must be in a zone of its own warehouse.
    public function testRejectsALocationInAnUnknownZone(): void
    {
        $warehouse = WarehouseMother::warehouse();
        $unknown = WarehouseMother::zoneId();

        self::assertDomainError(
            'warehouse.zone_unknown',
            ['zoneId' => $unknown->value],
            static fn (): Location => WarehouseMother::defineLocation($warehouse, $unknown),
        );
    }

    // WH-05: a zone that belongs to another warehouse does not count.
    public function testRejectsALocationInAZoneOfAnotherWarehouse(): void
    {
        $warehouse = WarehouseMother::warehouse(code: 'MAD1');
        $other = WarehouseMother::warehouse(code: 'MAD2');
        $zoneOfOther = WarehouseMother::zoneId();
        WarehouseMother::addZone($other, $zoneOfOther);

        self::assertDomainError(
            'warehouse.zone_unknown',
            ['zoneId' => $zoneOfOther->value],
            static fn (): Location => WarehouseMother::defineLocation($warehouse, $zoneOfOther),
        );
    }

    // WH-03: a location code is unique within its warehouse.
    public function testRejectsALocationCodeAlreadyUsedInTheWarehouse(): void
    {
        $warehouse = WarehouseMother::warehouse();
        $zoneId = WarehouseMother::zoneId();
        WarehouseMother::addZone($warehouse, $zoneId);
        $registry = new InMemoryLocationCodes()->take($warehouse->id(), LocationCode::fromString('A-03-02'));

        self::assertDomainError(
            'warehouse.location_code_already_used',
            ['code' => 'A-03-02'],
            static fn (): Location => WarehouseMother::defineLocation($warehouse, $zoneId, 'a-03-02', $registry),
        );
    }

    // WH-03: uniqueness is within one warehouse, so another warehouse can use the same code.
    public function testAnotherWarehouseCanUseTheSameLocationCode(): void
    {
        $first = WarehouseMother::warehouse(code: 'MAD1');
        $second = WarehouseMother::warehouse(code: 'MAD2');
        $zoneId = WarehouseMother::zoneId();
        WarehouseMother::addZone($second, $zoneId);
        $registry = new InMemoryLocationCodes()->take($first->id(), LocationCode::fromString('A-03-02'));

        $location = WarehouseMother::defineLocation($second, $zoneId, 'A-03-02', $registry);

        self::assertSame('A-03-02', $location->code()->value);
    }
}
