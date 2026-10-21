<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

use App\Shared\Domain\Identifier;

/**
 * A location, seen from Inbound: the dock where a receipt takes place. Only the identifier is
 * held: the location lives in Warehouse (ADR 0003).
 */
final readonly class LocationId extends Identifier
{
}
