<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Infrastructure\Http\UriVariable;
use App\Warehouse\Application\ListWarehouses\ListWarehousesHandler;
use App\Warehouse\Application\WarehouseLookup;
use App\Warehouse\Infrastructure\Http\Resource\WarehouseResource;

/**
 * @implements ProviderInterface<WarehouseResource>
 */
final readonly class WarehouseProvider implements ProviderInterface
{
    public function __construct(
        private WarehouseLookup $lookup,
        private ListWarehousesHandler $listWarehouses,
    ) {
    }

    /**
     * @return WarehouseResource|list<WarehouseResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): WarehouseResource|array
    {
        if ($operation instanceof CollectionOperationInterface) {
            return array_map(WarehouseResource::fromDomain(...), ($this->listWarehouses)());
        }

        return WarehouseResource::fromDomain($this->lookup->get(UriVariable::string($uriVariables, 'id')));
    }
}
