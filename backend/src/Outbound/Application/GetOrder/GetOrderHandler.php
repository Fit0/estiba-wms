<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetOrder;

use App\Outbound\Application\OrderLookup;
use App\Outbound\Domain\Exception\OrderNotFound;
use App\Outbound\Domain\Order;
use App\Shared\Application\Bus\QueryHandler;

final readonly class GetOrderHandler implements QueryHandler
{
    public function __construct(private OrderLookup $lookup)
    {
    }

    /**
     * @throws OrderNotFound
     */
    public function __invoke(GetOrder $query): Order
    {
        return $this->lookup->get($query->orderId);
    }
}
