<?php

declare(strict_types=1);

namespace App\Demo\Fashion;

use App\Warehouse\Domain\LocationCapacity;
use App\Warehouse\Domain\LocationType;

final readonly class DemoLocation
{
    public function __construct(
        public string $zone,
        public string $code,
        public LocationType $type,
        public LocationCapacity $capacity,
        public int $pickSequence,
    ) {
    }
}
