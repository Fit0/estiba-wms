<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Outbound\Application\ConfirmPickLine\ConfirmPickLine;
use App\Outbound\Infrastructure\Http\Input\ConfirmPickLineInput;
use App\Outbound\Infrastructure\Http\Resource\PickListResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<ConfirmPickLineInput, PickListResource>
 */
final readonly class ConfirmPickLineProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PickListResource
    {
        return PickListResource::fromDomain($this->commandBus->dispatch(new ConfirmPickLine(UriVariable::string($uriVariables, 'id'), (int) UriVariable::string($uriVariables, 'position'), $data->location, $data->gtin, $data->lot, $data->units, $data->reason)));
    }
}
