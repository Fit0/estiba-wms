<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Putaway;

use App\Inbound\Domain\LocationId;

/**
 * Where an operator put the goods, and what the warehouse said about that location at the moment
 * of the confirmation (INB-11). The application layer finds out; the task decides.
 */
final readonly class PutawayDestination
{
    public function __construct(
        public LocationId $locationId,
        public bool $active,
        public bool $storesTheGoods,
        public bool $suitsTheStorageClass,
        public bool $hasRoom,
    ) {
    }
}
