<?php

declare(strict_types=1);

namespace App\Warehouse\Application\GetLocation;

use App\Shared\Application\Bus\QueryHandler;
use App\Warehouse\Application\LocationLookup;
use App\Warehouse\Domain\Exception\LocationNotFound;
use App\Warehouse\Domain\Location;

final readonly class GetLocationHandler implements QueryHandler
{
    public function __construct(private LocationLookup $lookup)
    {
    }

    /**
     * @throws LocationNotFound
     */
    public function __invoke(GetLocation $query): Location
    {
        return $this->lookup->get($query->locationId);
    }
}
