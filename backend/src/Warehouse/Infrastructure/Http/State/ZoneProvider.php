<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Infrastructure\Http\UriVariable;
use App\Warehouse\Application\ListWarehouses\ListWarehousesHandler;
use App\Warehouse\Domain\Exception\ZoneNotFound;
use App\Warehouse\Domain\ZoneId;
use App\Warehouse\Infrastructure\Http\Resource\ZoneResource;

/**
 * Finds a zone among the warehouses of the current tenant.
 *
 * @implements ProviderInterface<ZoneResource>
 */
final readonly class ZoneProvider implements ProviderInterface
{
    public function __construct(private ListWarehousesHandler $listWarehouses)
    {
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ZoneResource
    {
        $id = ZoneId::fromString(UriVariable::string($uriVariables, 'id'));

        foreach (($this->listWarehouses)() as $warehouse) {
            foreach ($warehouse->zones() as $zone) {
                if ($zone->id->equals($id)) {
                    return ZoneResource::fromDomain($warehouse->id(), $zone);
                }
            }
        }

        throw ZoneNotFound::forId($id->value);
    }
}
