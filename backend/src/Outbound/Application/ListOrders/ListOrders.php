<?php

declare(strict_types=1);

namespace App\Outbound\Application\ListOrders;

use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderStatus;
use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;

/**
 * The orders of the current tenant, optionally narrowed to one status.
 *
 * @implements Query<Page<Order>>
 */
final readonly class ListOrders implements Query
{
    public function __construct(
        public PageRequest $page,
        public ?OrderStatus $status = null,
    ) {
    }
}
