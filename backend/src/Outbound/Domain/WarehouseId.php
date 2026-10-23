<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Shared\Domain\Identifier;

/**
 * The warehouse an order is served from, seen from Outbound. The warehouse lives in Warehouse (ADR 0003).
 */
final readonly class WarehouseId extends Identifier
{
}
