<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\ProductFamily;
use App\Shared\Domain\StorageClass;
use App\Shared\Domain\TenantId;
use App\Warehouse\Domain\Event\WarehouseCreated;
use App\Warehouse\Domain\Event\ZoneDefined;
use App\Warehouse\Domain\Event\ZoneFamiliesAssigned;
use App\Warehouse\Domain\Exception\LocationCodeAlreadyUsed;
use App\Warehouse\Domain\Exception\UnknownZone;
use App\Warehouse\Domain\Exception\WarehouseCodeAlreadyUsed;
use App\Warehouse\Domain\Exception\ZoneCodeAlreadyUsed;

/**
 * A physical site and its zones. Locations are separate aggregates, because a warehouse has
 * thousands of them, but they are always created through {@see defineLocation()} so that
 * the rules that need the warehouse are checked.
 */
final class Warehouse extends AggregateRoot
{
    /** @var list<Zone> */
    private array $zones = [];

    private function __construct(
        private readonly WarehouseId $id,
        private readonly TenantId $tenantId,
        private readonly WarehouseCode $code,
        private readonly DisplayName $name,
    ) {
    }

    /**
     * @throws WarehouseCodeAlreadyUsed WH-01
     */
    public static function create(
        WarehouseId $id,
        TenantId $tenantId,
        WarehouseCode $code,
        DisplayName $name,
        WarehouseCodeRegistry $registry,
        \DateTimeImmutable $at,
    ): self {
        if ($registry->isTaken($tenantId, $code)) {
            throw WarehouseCodeAlreadyUsed::forCode($code);
        }

        $warehouse = new self($id, $tenantId, $code, $name);
        $warehouse->record(new WarehouseCreated($id, $tenantId, $code, $at));

        return $warehouse;
    }

    /**
     * @throws ZoneCodeAlreadyUsed WH-02
     */
    public function addZone(
        ZoneId $id,
        ZoneCode $code,
        DisplayName $name,
        StorageClass $storageClass,
        PickSequence $pickSequence,
        \DateTimeImmutable $at,
    ): void {
        foreach ($this->zones as $zone) {
            if ($zone->code->equals($code)) {
                throw ZoneCodeAlreadyUsed::forCode($code);
            }
        }

        $this->zones[] = new Zone($id, $code, $name, $storageClass, $pickSequence);
        $this->record(new ZoneDefined($this->id, $id, $code, $storageClass, $at));
    }

    /**
     * Sets the families the zone keeps, replacing the ones it had. An empty list keeps none.
     *
     * @param list<ProductFamily> $families
     *
     * @throws UnknownZone
     */
    public function assignFamiliesToZone(ZoneId $zoneId, array $families, \DateTimeImmutable $at): Zone
    {
        $zone = $this->zone($zoneId)->withFamilies($families);

        $this->zones = array_map(static fn (Zone $own): Zone => $own->id->equals($zoneId) ? $zone : $own, $this->zones);
        $this->record(new ZoneFamiliesAssigned($this->id, $zoneId, $zone->families, $at));

        return $zone;
    }

    /**
     * @throws UnknownZone             WH-05
     * @throws LocationCodeAlreadyUsed WH-03
     */
    public function defineLocation(
        LocationId $locationId,
        ZoneId $zoneId,
        LocationCode $code,
        LocationType $type,
        LocationCapacity $capacity,
        PickSequence $pickSequence,
        LocationCodeRegistry $registry,
        \DateTimeImmutable $at,
    ): Location {
        $this->zone($zoneId);

        if ($registry->isTaken($this->id, $code)) {
            throw LocationCodeAlreadyUsed::forCode($code);
        }

        return Location::define($locationId, $this->tenantId, $this->id, $zoneId, $code, $type, $capacity, $pickSequence, $at);
    }

    public function id(): WarehouseId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function code(): WarehouseCode
    {
        return $this->code;
    }

    public function name(): DisplayName
    {
        return $this->name;
    }

    /**
     * @return list<Zone>
     */
    public function zones(): array
    {
        return $this->zones;
    }

    /**
     * @throws UnknownZone
     */
    public function zoneWithId(ZoneId $id): Zone
    {
        return $this->zone($id);
    }

    public function hasZone(ZoneId $id): bool
    {
        return array_any($this->zones, static fn (Zone $zone): bool => $zone->id->equals($id));
    }

    private function zone(ZoneId $id): Zone
    {
        foreach ($this->zones as $zone) {
            if ($zone->id->equals($id)) {
                return $zone;
            }
        }

        throw UnknownZone::forId($id);
    }
}
