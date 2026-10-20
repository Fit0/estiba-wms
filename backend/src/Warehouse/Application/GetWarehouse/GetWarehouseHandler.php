<?php

declare(strict_types=1);

namespace App\Warehouse\Application\GetWarehouse;

use App\Shared\Application\Bus\QueryHandler;
use App\Warehouse\Application\WarehouseLookup;
use App\Warehouse\Domain\Exception\WarehouseNotFound;
use App\Warehouse\Domain\Warehouse;

final readonly class GetWarehouseHandler implements QueryHandler
{
    public function __construct(private WarehouseLookup $lookup)
    {
    }

    /**
     * @throws WarehouseNotFound
     */
    public function __invoke(GetWarehouse $query): Warehouse
    {
        return $this->lookup->get($query->warehouseId);
    }
}
