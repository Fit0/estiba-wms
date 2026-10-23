<?php

declare(strict_types=1);

namespace App\Warehouse\Application;

use App\Shared\Domain\TenantId;
use App\Warehouse\Application\Contract\WarehouseDirectory;
use App\Warehouse\Application\Contract\WarehouseSnapshot;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseId;
use App\Warehouse\Domain\WarehouseRepository;

/**
 * Answers the {@see WarehouseDirectory} contract from the repository.
 */
final readonly class TenantWarehouses implements WarehouseDirectory
{
    public function __construct(private WarehouseRepository $warehouses)
    {
    }

    #[\Override]
    public function findById(TenantId $tenantId, string $warehouseId): ?WarehouseSnapshot
    {
        $warehouse = $this->warehouses->find(WarehouseId::fromString($warehouseId));
        if (!$warehouse instanceof Warehouse || !$warehouse->tenantId()->equals($tenantId)) {
            return null;
        }

        return new WarehouseSnapshot($warehouse->id()->value, $warehouse->code()->value, $warehouse->name()->value);
    }
}
