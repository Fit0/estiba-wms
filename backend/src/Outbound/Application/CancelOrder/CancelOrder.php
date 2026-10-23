<?php

declare(strict_types=1);

namespace App\Outbound\Application\CancelOrder;

use App\Outbound\Domain\Order;
use App\Shared\Application\Bus\Command;

/**
 * Cancels an order and gives back the stock reserved for it (OUT-04).
 *
 * @implements Command<Order>
 */
final readonly class CancelOrder implements Command
{
    public function __construct(public string $orderId)
    {
    }
}
