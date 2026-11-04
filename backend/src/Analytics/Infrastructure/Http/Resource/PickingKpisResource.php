<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure\Http\Resource;

/**
 * Picking measured on the pick lists completed in the period. `linesPerHour` is the stops confirmed
 * per hour spent between starting and completing a list, and `shortPickPercent` the share of the
 * units asked that were not found.
 */
final readonly class PickingKpisResource
{
    public function __construct(
        public int $listsCompleted,
        public int $linesPicked,
        public int $unitsPicked,
        public ?float $linesPerHour,
        public ?float $averageListMinutes,
        public int $shortPickLines,
        public int $unitsShort,
        public ?float $shortPickPercent,
    ) {
    }
}
