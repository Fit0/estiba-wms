<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;

/**
 * There is no delete method on purpose: locations are blocked, never deleted, because
 * stock history keeps pointing at them (WH-07).
 */
interface LocationRepository extends LocationCodeRegistry
{
    public function find(LocationId $id): ?Location;

    /**
     * The locations of a warehouse, ordered by code, optionally narrowed to a zone and a status.
     *
     * @return Page<Location>
     */
    public function search(WarehouseId $warehouseId, ?ZoneId $zoneId, ?LocationStatus $status, PageRequest $page): Page;

    public function save(Location $location): void;
}
