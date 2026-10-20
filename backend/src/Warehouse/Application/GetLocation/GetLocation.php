<?php

declare(strict_types=1);

namespace App\Warehouse\Application\GetLocation;

use App\Shared\Application\Bus\Query;
use App\Warehouse\Domain\Location;

/**
 * One location of the current tenant.
 *
 * @implements Query<Location>
 */
final readonly class GetLocation implements Query
{
    public function __construct(public string $locationId)
    {
    }
}
