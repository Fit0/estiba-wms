<?php

declare(strict_types=1);

namespace App\Warehouse\Application\GetWarehouse;

use App\Shared\Application\Bus\Query;
use App\Warehouse\Domain\Warehouse;

/**
 * One warehouse of the current tenant.
 *
 * @implements Query<Warehouse>
 */
final readonly class GetWarehouse implements Query
{
    public function __construct(public string $warehouseId)
    {
    }
}
