<?php

declare(strict_types=1);

namespace App\Warehouse\Application\DefineLocation;

use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;
use App\Shared\Application\IdGenerator;
use App\Warehouse\Application\WarehouseLookup;
use App\Warehouse\Domain\Location;
use App\Warehouse\Domain\LocationCapacity;
use App\Warehouse\Domain\LocationCode;
use App\Warehouse\Domain\LocationId;
use App\Warehouse\Domain\LocationRepository;
use App\Warehouse\Domain\PickSequence;
use App\Warehouse\Domain\ZoneId;

final readonly class DefineLocationHandler implements CommandHandler
{
    public function __construct(
        private WarehouseLookup $lookup,
        private LocationRepository $locations,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(DefineLocation $command): Location
    {
        $warehouse = $this->lookup->get($command->warehouseId);

        $location = $warehouse->defineLocation(
            LocationId::fromString($this->ids->next()),
            ZoneId::fromString($command->zoneId),
            LocationCode::fromString($command->code),
            $command->type,
            LocationCapacity::of($command->maxWeightKg, $command->maxVolumeLitres, $command->maxPalletPositions),
            PickSequence::of($command->pickSequence),
            $this->locations,
            $this->clock->now(),
        );

        $this->locations->save($location);

        return $location;
    }
}
