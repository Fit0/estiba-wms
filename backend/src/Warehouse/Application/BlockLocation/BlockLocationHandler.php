<?php

declare(strict_types=1);

namespace App\Warehouse\Application\BlockLocation;

use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;
use App\Warehouse\Application\LocationLookup;
use App\Warehouse\Domain\BlockReason;
use App\Warehouse\Domain\Location;

final readonly class BlockLocationHandler implements CommandHandler
{
    public function __construct(
        private LocationLookup $lookup,
        private Clock $clock,
    ) {
    }

    public function __invoke(BlockLocation $command): Location
    {
        $location = $this->lookup->get($command->locationId);

        $location->block(BlockReason::fromString($command->reason), $this->clock->now());

        return $location;
    }
}
