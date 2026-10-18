<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

use App\Shared\Domain\TenantId;

interface WarehouseRepository extends WarehouseCodeRegistry
{
    public function find(WarehouseId $id): ?Warehouse;

    /**
     * Every warehouse of the tenant, ordered by code. A tenant has a handful, so it is not paged.
     *
     * @return list<Warehouse>
     */
    public function findAllOf(TenantId $tenantId): array;

    public function save(Warehouse $warehouse): void;
}
