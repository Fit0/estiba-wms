<?php

declare(strict_types=1);

namespace App\Outbound\Application\AcceptBackorder;

use App\Outbound\Application\OrderLookup;
use App\Outbound\Domain\Order;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;

final readonly class AcceptBackorderHandler implements CommandHandler
{
    public function __construct(
        private OrderLookup $lookup,
        private EventBus $events,
    ) {
    }

    public function __invoke(AcceptBackorder $command): Order
    {
        $order = $this->lookup->get($command->orderId);
        $order->acceptBackorder();

        $this->events->publish(...$order->pullEvents());

        return $order;
    }
}
