<?php

declare(strict_types=1);

namespace App\Warehouse\Application\CreateWarehouse;

use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Application\IdGenerator;
use App\Warehouse\Domain\DisplayName;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseCode;
use App\Warehouse\Domain\WarehouseId;
use App\Warehouse\Domain\WarehouseRepository;

/**
 * The events the warehouse raises stay on it until the event bus exists (E03-02).
 * The caller owns the transaction and flushes.
 */
final readonly class CreateWarehouseHandler
{
    public function __construct(
        private WarehouseRepository $warehouses,
        private CurrentTenant $tenant,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreateWarehouse $command): Warehouse
    {
        $warehouse = Warehouse::create(
            WarehouseId::fromString($this->ids->next()),
            $this->tenant->id(),
            WarehouseCode::fromString($command->code),
            DisplayName::fromString($command->name),
            $this->warehouses,
            $this->clock->now(),
        );

        $this->warehouses->save($warehouse);

        return $warehouse;
    }
}
