<?php

declare(strict_types=1);

namespace App\Outbound\Application\AllocateOrder;

use App\Outbound\Domain\Order;
use App\Shared\Application\Bus\Command;

/**
 * Asks the stock to cover the lines of an order that are not fully covered. Doing it again, when
 * stock has arrived, covers more and never reserves twice.
 *
 * @implements Command<Order>
 */
final readonly class AllocateOrder implements Command
{
    public function __construct(public string $orderId)
    {
    }
}
