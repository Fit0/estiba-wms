<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Outbound\Application\DispatchShipment\DispatchShipment;
use App\Outbound\Infrastructure\Http\Input\DispatchShipmentInput;
use App\Outbound\Infrastructure\Http\Resource\ShipmentResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<DispatchShipmentInput, ShipmentResource>
 */
final readonly class DispatchShipmentProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ShipmentResource
    {
        return ShipmentResource::fromDomain($this->commandBus->dispatch(new DispatchShipment(UriVariable::string($uriVariables, 'id'), $data->carrier, $data->trackingNumber)));
    }
}
