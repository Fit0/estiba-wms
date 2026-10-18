<?php

declare(strict_types=1);

namespace App\Warehouse\Application\ListLocations;

use App\Shared\Domain\PageRequest;
use App\Warehouse\Domain\LocationStatus;

final readonly class ListLocations
{
    public function __construct(
        public string $warehouseId,
        public PageRequest $page,
        public ?string $zoneId = null,
        public ?LocationStatus $status = null,
    ) {
    }
}
