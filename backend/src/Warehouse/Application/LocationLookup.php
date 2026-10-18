<?php

declare(strict_types=1);

namespace App\Warehouse\Application;

use App\Shared\Application\CurrentTenant;
use App\Warehouse\Domain\Exception\LocationNotFound;
use App\Warehouse\Domain\Location;
use App\Warehouse\Domain\LocationId;
use App\Warehouse\Domain\LocationRepository;

/**
 * Finds a location of the current tenant. A location of another tenant is reported as not found.
 */
final readonly class LocationLookup
{
    public function __construct(
        private LocationRepository $locations,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws LocationNotFound
     */
    public function get(string $id): Location
    {
        $location = $this->locations->find(LocationId::fromString($id));

        if (!$location instanceof Location || !$location->tenantId()->equals($this->tenant->id())) {
            throw LocationNotFound::forId($id);
        }

        return $location;
    }
}
