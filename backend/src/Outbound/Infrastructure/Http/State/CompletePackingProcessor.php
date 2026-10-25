<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Outbound\Application\CompletePacking\CompletePacking;
use App\Outbound\Infrastructure\Http\Resource\ShipmentResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<null, ShipmentResource>
 */
final readonly class CompletePackingProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ShipmentResource
    {
        return ShipmentResource::fromDomain($this->commandBus->dispatch(new CompletePacking(UriVariable::string($uriVariables, 'id'))));
    }
}
