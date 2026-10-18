<?php

declare(strict_types=1);

namespace App\Tests\Unit\Warehouse\Domain;

use App\Shared\Domain\StorageClass;
use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\Ids;
use App\Warehouse\Domain\DisplayName;
use App\Warehouse\Domain\Location;
use App\Warehouse\Domain\LocationCapacity;
use App\Warehouse\Domain\LocationCode;
use App\Warehouse\Domain\LocationId;
use App\Warehouse\Domain\LocationType;
use App\Warehouse\Domain\PickSequence;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseCode;
use App\Warehouse\Domain\WarehouseId;
use App\Warehouse\Domain\ZoneCode;
use App\Warehouse\Domain\ZoneId;

/**
 * Builds valid objects so each test only spells out what it is about.
 */
final class WarehouseMother
{
    public static function at(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-12T10:00:00+00:00');
    }

    public static function tenantId(): TenantId
    {
        return TenantId::fromString(Ids::next());
    }

    public static function warehouseId(): WarehouseId
    {
        return WarehouseId::fromString(Ids::next());
    }

    public static function zoneId(): ZoneId
    {
        return ZoneId::fromString(Ids::next());
    }

    public static function locationId(): LocationId
    {
        return LocationId::fromString(Ids::next());
    }

    public static function warehouse(?TenantId $tenantId = null, string $code = 'MAD1'): Warehouse
    {
        return Warehouse::create(
            self::warehouseId(),
            $tenantId ?? self::tenantId(),
            WarehouseCode::fromString($code),
            DisplayName::fromString('Madrid central warehouse'),
            new InMemoryWarehouseCodes(),
            self::at(),
        );
    }

    public static function addZone(Warehouse $warehouse, ZoneId $id, string $code = 'FAST'): void
    {
        $warehouse->addZone(
            $id,
            ZoneCode::fromString($code),
            DisplayName::fromString('Fast movers'),
            StorageClass::Ambient,
            PickSequence::of(1),
            self::at(),
        );
    }

    public static function defineLocation(
        Warehouse $warehouse,
        ZoneId $zoneId,
        string $code = 'A-03-02',
        ?InMemoryLocationCodes $registry = null,
    ): Location {
        return $warehouse->defineLocation(
            self::locationId(),
            $zoneId,
            LocationCode::fromString($code),
            LocationType::Reserve,
            LocationCapacity::of(maxWeightKg: 1000, maxPalletPositions: 1),
            PickSequence::of(10),
            $registry ?? new InMemoryLocationCodes(),
            self::at(),
        );
    }

    /**
     * A new active location, with its creation event still pending.
     */
    public static function location(string $code = 'A-03-02'): Location
    {
        $warehouse = self::warehouse();
        $zoneId = self::zoneId();
        self::addZone($warehouse, $zoneId);

        return self::defineLocation($warehouse, $zoneId, $code);
    }
}
