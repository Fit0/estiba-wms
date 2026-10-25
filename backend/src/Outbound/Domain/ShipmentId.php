<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Shared\Domain\Identifier;

/**
 * The parcels of one order, on their way to the carrier.
 */
final readonly class ShipmentId extends Identifier
{
}
