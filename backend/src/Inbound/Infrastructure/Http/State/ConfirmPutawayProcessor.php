<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Inbound\Application\ConfirmPutaway\ConfirmPutaway;
use App\Inbound\Infrastructure\Http\Input\ConfirmPutawayInput;
use App\Inbound\Infrastructure\Http\Resource\PutawayTaskResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<ConfirmPutawayInput, PutawayTaskResource>
 */
final readonly class ConfirmPutawayProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PutawayTaskResource
    {
        return PutawayTaskResource::fromDomain($this->commandBus->dispatch(new ConfirmPutaway(
            UriVariable::string($uriVariables, 'id'),
            $data->locationId,
            $data->reason,
        )));
    }
}
