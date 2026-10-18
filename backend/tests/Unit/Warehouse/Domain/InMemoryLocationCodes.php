<?php

declare(strict_types=1);

namespace App\Tests\Unit\Warehouse\Domain;

use App\Warehouse\Domain\LocationCode;
use App\Warehouse\Domain\LocationCodeRegistry;
use App\Warehouse\Domain\WarehouseId;

/**
 * Stands in for the database in unit tests: it remembers which location codes are taken.
 */
final class InMemoryLocationCodes implements LocationCodeRegistry
{
    /** @var array<string, true> */
    private array $taken = [];

    public function take(WarehouseId $warehouseId, LocationCode $code): self
    {
        $this->taken[$warehouseId->value.'|'.$code->value] = true;

        return $this;
    }

    public function isTaken(WarehouseId $warehouseId, LocationCode $code): bool
    {
        return isset($this->taken[$warehouseId->value.'|'.$code->value]);
    }
}
