<?php

declare(strict_types=1);

namespace App\Outbound\Application\CompletePacking;

use App\Outbound\Domain\Shipment;
use App\Shared\Application\Bus\Command;

/**
 * Declares the shipment packed: every picked unit is in a closed parcel.
 *
 * @implements Command<Shipment>
 */
final readonly class CompletePacking implements Command
{
    public function __construct(public string $shipmentId)
    {
    }
}
