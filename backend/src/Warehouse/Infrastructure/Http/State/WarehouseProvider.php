<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Infrastructure\Http\UriVariable;
use App\Warehouse\Application\GetWarehouse\GetWarehouse;
use App\Warehouse\Application\ListWarehouses\ListWarehouses;
use App\Warehouse\Infrastructure\Http\Resource\WarehouseResource;

/**
 * @implements ProviderInterface<WarehouseResource>
 */
final readonly class WarehouseProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return WarehouseResource|list<WarehouseResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): WarehouseResource|array
    {
        if ($operation instanceof CollectionOperationInterface) {
            return array_map(WarehouseResource::fromDomain(...), $this->queryBus->ask(new ListWarehouses()));
        }

        return WarehouseResource::fromDomain($this->queryBus->ask(new GetWarehouse(UriVariable::string($uriVariables, 'id'))));
    }
}
