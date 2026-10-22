<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Shared\Domain\Identifier;

/**
 * A warehouse, seen from Inventory. Only the identifier is held: the warehouse lives in Warehouse (ADR 0003).
 */
final readonly class WarehouseId extends Identifier
{
}
