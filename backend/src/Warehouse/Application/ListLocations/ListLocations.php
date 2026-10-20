<?php

declare(strict_types=1);

namespace App\Warehouse\Application\ListLocations;

use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Warehouse\Domain\Location;
use App\Warehouse\Domain\LocationStatus;

/**
 * @implements Query<Page<Location>>
 */
final readonly class ListLocations implements Query
{
    public function __construct(
        public string $warehouseId,
        public PageRequest $page,
        public ?string $zoneId = null,
        public ?LocationStatus $status = null,
    ) {
    }
}
