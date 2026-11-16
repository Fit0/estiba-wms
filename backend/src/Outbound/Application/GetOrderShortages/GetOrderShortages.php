<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetOrderShortages;

use App\Shared\Application\Bus\Query;

/**
 * Why each line of an order that is not fully reserved is not: where its stock is and what keeps it
 * from being used (ADR 0058).
 *
 * @implements Query<list<LineShortage>>
 */
final readonly class GetOrderShortages implements Query
{
    public function __construct(public string $orderId)
    {
    }
}
