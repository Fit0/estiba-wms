<?php

declare(strict_types=1);

namespace App\Outbound\Application;

use App\Outbound\Domain\Exception\OrderNotFound;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\OrderRepository;
use App\Shared\Application\CurrentTenant;

/**
 * Finds an order of the current tenant. An order of another tenant is reported as not found.
 */
final readonly class OrderLookup
{
    public function __construct(
        private OrderRepository $orders,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws OrderNotFound
     */
    public function get(string $id): Order
    {
        $order = $this->orders->find(OrderId::fromString($id));

        if (!$order instanceof Order || !$order->tenantId()->equals($this->tenant->id())) {
            throw OrderNotFound::forId($id);
        }

        return $order;
    }
}
