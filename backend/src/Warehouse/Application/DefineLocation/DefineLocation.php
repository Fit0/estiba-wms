<?php

declare(strict_types=1);

namespace App\Warehouse\Application\DefineLocation;

use App\Shared\Application\Bus\Command;
use App\Warehouse\Domain\Location;
use App\Warehouse\Domain\LocationType;

/**
 * @implements Command<Location>
 */
final readonly class DefineLocation implements Command
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
