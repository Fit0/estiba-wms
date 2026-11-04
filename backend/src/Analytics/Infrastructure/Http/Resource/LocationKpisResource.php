<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure\Http\Resource;

/**
 * Active storage locations (reserve and pick face) and how many of them hold stock right now.
 */
final readonly class LocationKpisResource
{
    public function __construct(
        public int $storage,
        public int $occupied,
        public ?float $occupancyPercent,
    ) {
    }
}
