<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\Input;

use App\Warehouse\Domain\LocationType;

final readonly class DefineLocationInput
{
    public function __construct(
        public string $zoneId,
        public string $code,
        public LocationType $type,
        public int $pickSequence = 0,
        public ?int $maxWeightKg = null,
        public ?int $maxVolumeLitres = null,
        public ?int $maxPalletPositions = null,
    ) {
    }
}
