<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetShipment;

use App\Outbound\Domain\Shipment;
use App\Shared\Application\Bus\Query;

/**
 * One shipment of the current tenant.
 *
 * @implements Query<Shipment>
 */
final readonly class GetShipment implements Query
{
    public function __construct(public string $shipmentId)
    {
    }
}
