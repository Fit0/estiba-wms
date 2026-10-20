<?php

declare(strict_types=1);

namespace App\Warehouse\Application\ListWarehouses;

use App\Shared\Application\Bus\Query;
use App\Warehouse\Domain\Warehouse;

/**
 * Every warehouse of the current tenant.
 *
 * @implements Query<list<Warehouse>>
 */
final readonly class ListWarehouses implements Query
{
}
