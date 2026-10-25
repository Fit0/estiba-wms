<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Outbound\Application\StartPacking\StartPacking;
use App\Outbound\Infrastructure\Http\Input\StartPackingInput;
use App\Outbound\Infrastructure\Http\Resource\ShipmentResource;
use App\Shared\Application\Bus\CommandBus;

/**
 * @implements ProcessorInterface<StartPackingInput, ShipmentResource>
 */
final readonly class StartPackingProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ShipmentResource
    {
        return ShipmentResource::fromDomain($this->commandBus->dispatch(new StartPacking($data->orderId)));
    }
}
