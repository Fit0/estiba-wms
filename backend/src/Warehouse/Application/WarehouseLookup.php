<?php

declare(strict_types=1);

namespace App\Warehouse\Application;

use App\Shared\Application\CurrentTenant;
use App\Warehouse\Domain\Exception\WarehouseNotFound;
use App\Warehouse\Domain\Exception\ZoneNotFound;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseId;
use App\Warehouse\Domain\WarehouseRepository;
use App\Warehouse\Domain\ZoneId;

/**
 * Finds a warehouse of the current tenant. A warehouse of another tenant is reported as
 * not found, so one tenant cannot tell that another tenant's identifier exists.
 */
final readonly class WarehouseLookup
{
    public function __construct(
        private WarehouseRepository $warehouses,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws WarehouseNotFound
     */
    public function get(string $id): Warehouse
    {
        $warehouse = $this->warehouses->find(WarehouseId::fromString($id));

        if (!$warehouse instanceof Warehouse || !$warehouse->tenantId()->equals($this->tenant->id())) {
            throw WarehouseNotFound::forId($id);
        }

        return $warehouse;
    }

    /**
     * The warehouse of the current tenant that has the zone. A tenant has a handful of warehouses.
     *
     * @throws ZoneNotFound
     */
    public function withZone(ZoneId $zoneId): Warehouse
    {
        foreach ($this->warehouses->findAllOf($this->tenant->id()) as $warehouse) {
            if ($warehouse->hasZone($zoneId)) {
                return $warehouse;
            }
        }

        throw ZoneNotFound::forId($zoneId->value);
    }
}
