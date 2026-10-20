<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Shared\Domain\Identifier;

/**
 * A location, seen from Inventory. Only the identifier is held: the location lives in Warehouse (ADR 0003).
 */
final readonly class LocationId extends Identifier
{
}
