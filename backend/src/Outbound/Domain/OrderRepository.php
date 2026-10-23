<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;

/**
 * There is no delete method: an order that will not leave is cancelled, because its reservations
 * and, later, its picks and shipments refer to it.
 */
interface OrderRepository extends OrderReferenceRegistry
{
    public function find(OrderId $id): ?Order;

    /**
     * The tenant's orders, the most urgent first and then the earliest cut-off, optionally narrowed
     * to one status.
     *
     * @return Page<Order>
     */
    public function search(TenantId $tenantId, ?OrderStatus $status, PageRequest $page): Page;

    public function save(Order $order): void;
}
