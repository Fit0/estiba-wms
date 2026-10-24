<?php

declare(strict_types=1);

namespace App\Outbound\Application;

use App\Outbound\Domain\Exception\NoStagingLocation;
use App\Outbound\Domain\WarehouseId;
use App\Shared\Application\CurrentTenant;
use App\Warehouse\Application\Contract\LocationDirectory;
use App\Warehouse\Application\Contract\LocationSnapshot;

/**
 * Where the picked units wait for packing: the first active staging location of the warehouse in the
 * order of the picking route.
 */
final readonly class StagingLocations
{
    public function __construct(
        private LocationDirectory $locations,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return string the identifier of the location
     *
     * @throws NoStagingLocation when the warehouse has none
     */
    public function of(WarehouseId $warehouseId): string
    {
        $staging = $this->locations->activeLocationsOf($this->tenant->id(), $warehouseId->value, [LocationSnapshot::TYPE_STAGING]);

        return ($staging[0] ?? throw NoStagingLocation::in($warehouseId->value))->id;
    }
}
