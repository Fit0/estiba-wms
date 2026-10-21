<?php

declare(strict_types=1);

namespace App\Warehouse\Application;

use App\Shared\Domain\TenantId;
use App\Warehouse\Application\Contract\LocationDirectory;
use App\Warehouse\Application\Contract\LocationSnapshot;
use App\Warehouse\Domain\Location;
use App\Warehouse\Domain\LocationId;
use App\Warehouse\Domain\LocationRepository;

/**
 * Answers the {@see LocationDirectory} contract from the location repository.
 */
final readonly class WarehouseLocations implements LocationDirectory
{
    public function __construct(private LocationRepository $locations)
    {
    }

    #[\Override]
    public function findById(TenantId $tenantId, string $locationId): ?LocationSnapshot
    {
        $location = $this->locations->find(LocationId::fromString($locationId));

        if (!$location instanceof Location || !$location->tenantId()->equals($tenantId)) {
            return null;
        }

        return new LocationSnapshot(
            $location->id()->value,
            $location->warehouseId()->value,
            $location->zoneId()->value,
            $location->code()->value,
            $location->type()->value,
            $location->isEligibleForStorage(),
        );
    }
}
