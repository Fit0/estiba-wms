<?php

declare(strict_types=1);

namespace App\Warehouse\Application\UnblockLocation;

use App\Shared\Application\Clock;
use App\Warehouse\Application\LocationLookup;
use App\Warehouse\Domain\Location;

final readonly class UnblockLocationHandler
{
    public function __construct(
        private LocationLookup $lookup,
        private Clock $clock,
    ) {
    }

    public function __invoke(UnblockLocation $command): Location
    {
        $location = $this->lookup->get($command->locationId);

        $location->unblock($this->clock->now());

        return $location;
    }
}
