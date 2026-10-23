<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetOrder;

use App\Outbound\Domain\Order;
use App\Shared\Application\Bus\Query;

/**
 * One order of the current tenant.
 *
 * @implements Query<Order>
 */
final readonly class GetOrder implements Query
{
    public function __construct(public string $orderId)
    {
    }
}
