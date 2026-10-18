<?php

declare(strict_types=1);

namespace App\Warehouse\Application\BlockLocation;

final readonly class BlockLocation
{
    public function __construct(
        public string $locationId,
        public string $reason,
    ) {
    }
}
