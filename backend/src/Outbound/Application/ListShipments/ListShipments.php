<?php

declare(strict_types=1);

namespace App\Outbound\Application\ListShipments;

use App\Outbound\Domain\Shipment;
use App\Outbound\Domain\ShipmentStatus;
use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;

/**
 * The shipments of the current tenant, optionally narrowed to one status.
 *
 * @implements Query<Page<Shipment>>
 */
final readonly class ListShipments implements Query
{
    public function __construct(
        public PageRequest $page,
        public ?ShipmentStatus $status = null,
    ) {
    }
}
