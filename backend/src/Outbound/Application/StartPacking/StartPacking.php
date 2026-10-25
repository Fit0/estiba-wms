<?php

declare(strict_types=1);

namespace App\Outbound\Application\StartPacking;

use App\Outbound\Domain\Shipment;
use App\Shared\Application\Bus\Command;

/**
 * Begins packing a picked order. The order has one shipment, and only one.
 *
 * @implements Command<Shipment>
 */
final readonly class StartPacking implements Command
{
    public function __construct(public string $orderId)
    {
    }
}
