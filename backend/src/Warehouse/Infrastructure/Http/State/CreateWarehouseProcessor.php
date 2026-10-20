<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Bus\CommandBus;
use App\Warehouse\Application\CreateWarehouse\CreateWarehouse;
use App\Warehouse\Infrastructure\Http\Input\CreateWarehouseInput;
use App\Warehouse\Infrastructure\Http\Resource\WarehouseResource;

/**
 * @implements ProcessorInterface<CreateWarehouseInput, WarehouseResource>
 */
final readonly class CreateWarehouseProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WarehouseResource
    {
        return WarehouseResource::fromDomain($this->commandBus->dispatch(new CreateWarehouse($data->code, $data->name)));
    }
}
