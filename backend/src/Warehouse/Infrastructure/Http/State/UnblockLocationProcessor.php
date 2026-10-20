<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;
use App\Warehouse\Application\UnblockLocation\UnblockLocation;
use App\Warehouse\Infrastructure\Http\Resource\LocationResource;

/**
 * @implements ProcessorInterface<null, LocationResource>
 */
final readonly class UnblockLocationProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LocationResource
    {
        return LocationResource::fromDomain($this->commandBus->dispatch(new UnblockLocation(UriVariable::string($uriVariables, 'id'))));
    }
}
