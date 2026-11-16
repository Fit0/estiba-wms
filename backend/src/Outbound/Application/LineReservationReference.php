<?php

declare(strict_types=1);

namespace App\Outbound\Application;

use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderLine;

/**
 * The reference Inventory knows the reservation of an order line by. Allocating and asking why a line
 * is not covered must use the same one, or the second would not find the reservation of the first.
 */
final readonly class LineReservationReference
{
    public static function of(Order $order, OrderLine $line): string
    {
        return \sprintf('order:%s:%d', $order->id()->value, $line->lineNumber);
    }
}
