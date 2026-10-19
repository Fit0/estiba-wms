<?php

declare(strict_types=1);

namespace App\Warehouse\Application\DefineLocation;

use App\Warehouse\Domain\LocationType;

final readonly class DefineLocation
{
    public function __construct(
        public string $warehouseId,
        public string $zoneId,
        public string $code,
        public LocationType $type,
        public int $pickSequence,
        public ?int $maxWeightKg = null,
        public ?int $maxVolumeLitres = null,
        public ?int $maxPalletPositions = null,
    ) {
    }
}
