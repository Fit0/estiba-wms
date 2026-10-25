<?php

declare(strict_types=1);

namespace App\Outbound\Application\ListShipments;

use App\Outbound\Domain\Shipment;
use App\Outbound\Domain\ShipmentRepository;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;

final readonly class ListShipmentsHandler implements QueryHandler
{
    public function __construct(
        private ShipmentRepository $shipments,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return Page<Shipment>
     */
    public function __invoke(ListShipments $query): Page
    {
        return $this->shipments->search($this->tenant->id(), $query->status, $query->page);
    }
}
