<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

/**
 * Lets {@see Warehouse::defineLocation()} check that a code is free within the warehouse (WH-03)
 * without knowing how locations are stored. A unique database constraint backs the check
 * against races.
 */
interface LocationCodeRegistry
{
    public function isTaken(WarehouseId $warehouseId, LocationCode $code): bool;
}
