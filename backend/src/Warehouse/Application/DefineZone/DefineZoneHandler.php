<?php

declare(strict_types=1);

namespace App\Warehouse\Application\DefineZone;

use App\Shared\Application\Clock;
use App\Shared\Application\IdGenerator;
use App\Warehouse\Application\WarehouseLookup;
use App\Warehouse\Domain\DisplayName;
use App\Warehouse\Domain\PickSequence;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseRepository;
use App\Warehouse\Domain\ZoneCode;
use App\Warehouse\Domain\ZoneId;

final readonly class DefineZoneHandler
{
    public function __construct(
        private WarehouseLookup $lookup,
        private WarehouseRepository $warehouses,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    /**
     * Returns the warehouse, whose last zone is the one just defined.
     */
    public function __invoke(DefineZone $command): Warehouse
    {
        $warehouse = $this->lookup->get($command->warehouseId);

        $warehouse->addZone(
            ZoneId::fromString($this->ids->next()),
            ZoneCode::fromString($command->code),
            DisplayName::fromString($command->name),
            $command->storageClass,
            PickSequence::of($command->pickSequence),
            $this->clock->now(),
        );

        $this->warehouses->save($warehouse);

        return $warehouse;
    }
}
