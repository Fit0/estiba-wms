<?php

declare(strict_types=1);

namespace App\Warehouse\Application\ListLocations;

use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Domain\Page;
use App\Warehouse\Application\WarehouseLookup;
use App\Warehouse\Domain\Location;
use App\Warehouse\Domain\LocationRepository;
use App\Warehouse\Domain\ZoneId;

final readonly class ListLocationsHandler implements QueryHandler
{
    public function __construct(
        private WarehouseLookup $lookup,
        private LocationRepository $locations,
    ) {
    }

    /**
     * @return Page<Location>
     */
    public function __invoke(ListLocations $query): Page
    {
        $warehouse = $this->lookup->get($query->warehouseId);

        return $this->locations->search(
            $warehouse->id(),
            null === $query->zoneId ? null : ZoneId::fromString($query->zoneId),
            $query->status,
            $query->page,
        );
    }
}
