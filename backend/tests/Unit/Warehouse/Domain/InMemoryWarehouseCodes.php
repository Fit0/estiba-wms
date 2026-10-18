<?php

declare(strict_types=1);

namespace App\Tests\Unit\Warehouse\Domain;

use App\Shared\Domain\TenantId;
use App\Warehouse\Domain\WarehouseCode;
use App\Warehouse\Domain\WarehouseCodeRegistry;

/**
 * Stands in for the database in unit tests: it remembers which warehouse codes are taken.
 */
final class InMemoryWarehouseCodes implements WarehouseCodeRegistry
{
    /** @var array<string, true> */
    private array $taken = [];

    public function take(TenantId $tenantId, WarehouseCode $code): self
    {
        $this->taken[$tenantId->value.'|'.$code->value] = true;

        return $this;
    }

    public function isTaken(TenantId $tenantId, WarehouseCode $code): bool
    {
        return isset($this->taken[$tenantId->value.'|'.$code->value]);
    }
}
