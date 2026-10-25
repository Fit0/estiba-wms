<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;

/**
 * There is no delete method: the parcels of a shipment have labels out in the world.
 */
interface ShipmentRepository
{
    public function find(ShipmentId $id): ?Shipment;

    /**
     * The shipment that packs an order, if packing has started. There is one at most (OUT-20).
     */
    public function findByOrder(OrderId $orderId): ?Shipment;

    /**
     * The tenant's shipments, the latest first, optionally narrowed to one status.
     *
     * @return Page<Shipment>
     */
    public function search(TenantId $tenantId, ?ShipmentStatus $status, PageRequest $page): Page;

    public function save(Shipment $shipment): void;
}
