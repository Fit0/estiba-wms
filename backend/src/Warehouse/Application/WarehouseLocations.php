<?php

declare(strict_types=1);

namespace App\Warehouse\Application;

use App\Shared\Domain\TenantId;
use App\Warehouse\Application\Contract\LocationDirectory;
use App\Warehouse\Application\Contract\LocationSnapshot;
use App\Warehouse\Domain\Location;
use App\Warehouse\Domain\LocationId;
use App\Warehouse\Domain\LocationRepository;
use App\Warehouse\Domain\LocationType;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseId;
use App\Warehouse\Domain\WarehouseRepository;

/**
 * Answers the {@see LocationDirectory} contract from the repositories.
 */
final readonly class WarehouseLocations implements LocationDirectory
{
    public function __construct(
        private LocationRepository $locations,
        private WarehouseRepository $warehouses,
    ) {
    }

    #[\Override]
    public function findById(TenantId $tenantId, string $locationId): ?LocationSnapshot
    {
        $location = $this->locations->find(LocationId::fromString($locationId));
        if (!$location instanceof Location || !$location->tenantId()->equals($tenantId)) {
            return null;
        }

        $warehouse = $this->warehouses->find($location->warehouseId());

        return $warehouse instanceof Warehouse ? self::snapshotOf($location, $warehouse) : null;
    }

    #[\Override]
    public function activeLocationsOf(TenantId $tenantId, string $warehouseId, array $types): array
    {
        $warehouse = $this->warehouses->find(WarehouseId::fromString($warehouseId));
        if (!$warehouse instanceof Warehouse || !$warehouse->tenantId()->equals($tenantId)) {
            return [];
        }

        $wanted = array_values(array_filter(array_map(LocationType::tryFrom(...), $types)));
        $snapshots = array_map(
            static fn (Location $location): LocationSnapshot => self::snapshotOf($location, $warehouse),
            $this->locations->findActiveOfTypes($warehouse->id(), $wanted),
        );
        usort($snapshots, static fn (LocationSnapshot $a, LocationSnapshot $b): int => [$a->zonePickSequence, $a->pickSequence, $a->code] <=> [$b->zonePickSequence, $b->pickSequence, $b->code]);

        return $snapshots;
    }

    private static function snapshotOf(Location $location, Warehouse $warehouse): LocationSnapshot
    {
        $zone = $warehouse->zoneWithId($location->zoneId());
        $capacity = $location->capacity();

        return new LocationSnapshot(
            $location->id()->value,
            $location->warehouseId()->value,
            $location->zoneId()->value,
            $location->code()->value,
            $location->type()->value,
            $location->isEligibleForStorage(),
            $zone->storageClass,
            $zone->families,
            $zone->pickSequence->value,
            $location->pickSequence()->value,
            $capacity->maxWeightKg,
            $capacity->maxVolumeLitres,
        );
    }
}
