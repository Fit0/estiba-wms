<?php

declare(strict_types=1);

namespace App\Warehouse\Application\ListWarehouses;

use App\Shared\Application\CurrentTenant;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseRepository;

final readonly class ListWarehousesHandler
{
    public function __construct(
        private WarehouseRepository $warehouses,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return list<Warehouse>
     */
    public function __invoke(): array
    {
        return $this->warehouses->findAllOf($this->tenant->id());
    }
}
