<?php

declare(strict_types=1);

namespace App\Warehouse\Application;

use App\Shared\Domain\TenantId;
use App\Warehouse\Application\Contract\LocationCensus;
use App\Warehouse\Domain\LocationId;
use App\Warehouse\Domain\LocationRepository;
use App\Warehouse\Domain\LocationType;

/**
 * Answers the {@see LocationCensus} contract from the location repository.
 */
final readonly class WarehouseLocationCensus implements LocationCensus
{
    public function __construct(private LocationRepository $locations)
    {
    }

    #[\Override]
    public function activeStorageLocationIds(TenantId $tenantId): array
    {
        return array_map(
            static fn (LocationId $id): string => $id->value,
            $this->locations->activeIdsOfTypes($tenantId, [LocationType::Reserve, LocationType::PickFace]),
        );
    }
}
