<?php

declare(strict_types=1);

namespace App\Outbound\Application\AcceptBackorder;

use App\Outbound\Domain\Order;
use App\Shared\Application\Bus\Command;

/**
 * The customer accepts to receive less than ordered, so the part that is covered can go to the floor (OUT-06).
 *
 * @implements Command<Order>
 */
final readonly class AcceptBackorder implements Command
{
    public function __construct(public string $orderId)
    {
    }
}
