<?php

declare(strict_types=1);

namespace App\Outbound\Application\ListOrders;

use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderRepository;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;

final readonly class ListOrdersHandler implements QueryHandler
{
    public function __construct(
        private OrderRepository $orders,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return Page<Order>
     */
    public function __invoke(ListOrders $query): Page
    {
        return $this->orders->search($this->tenant->id(), $query->status, $query->page);
    }
}
