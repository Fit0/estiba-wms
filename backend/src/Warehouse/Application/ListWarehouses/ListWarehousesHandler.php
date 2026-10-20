<?php

declare(strict_types=1);

namespace App\Warehouse\Application\ListWarehouses;

use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseRepository;

final readonly class ListWarehousesHandler implements QueryHandler
{
    public function __construct(
        private WarehouseRepository $warehouses,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return list<Warehouse>
     */
    public function __invoke(ListWarehouses $query): array
    {
        return $this->warehouses->findAllOf($this->tenant->id());
    }
}
