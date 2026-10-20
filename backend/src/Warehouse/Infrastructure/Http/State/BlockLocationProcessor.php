<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;
use App\Warehouse\Application\BlockLocation\BlockLocation;
use App\Warehouse\Infrastructure\Http\Input\BlockLocationInput;
use App\Warehouse\Infrastructure\Http\Resource\LocationResource;

/**
 * @implements ProcessorInterface<BlockLocationInput, LocationResource>
 */
final readonly class BlockLocationProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LocationResource
    {
        return LocationResource::fromDomain($this->commandBus->dispatch(new BlockLocation(UriVariable::string($uriVariables, 'id'), $data->reason)));
    }
}
