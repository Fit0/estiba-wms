<?php

declare(strict_types=1);

namespace App\Warehouse\Application\AssignZoneFamilies;

use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;
use App\Shared\Domain\ProductFamily;
use App\Warehouse\Application\WarehouseLookup;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\ZoneId;

final readonly class AssignZoneFamiliesHandler implements CommandHandler
{
    public function __construct(
        private WarehouseLookup $lookup,
        private Clock $clock,
    ) {
    }

    /**
     * Returns the warehouse the zone belongs to.
     */
    public function __invoke(AssignZoneFamilies $command): Warehouse
    {
        $zoneId = ZoneId::fromString($command->zoneId);
        $warehouse = $this->lookup->withZone($zoneId);

        $warehouse->assignFamiliesToZone(
            $zoneId,
            array_map(ProductFamily::fromString(...), $command->families),
            $this->clock->now(),
        );

        return $warehouse;
    }
}
