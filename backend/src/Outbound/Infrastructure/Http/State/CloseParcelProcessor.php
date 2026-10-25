<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Outbound\Application\CloseParcel\CloseParcel;
use App\Outbound\Infrastructure\Http\Input\CloseParcelContents;
use App\Outbound\Infrastructure\Http\Input\CloseParcelInput;
use App\Outbound\Infrastructure\Http\Resource\ShipmentResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<CloseParcelInput, ShipmentResource>
 */
final readonly class CloseParcelProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ShipmentResource
    {
        return ShipmentResource::fromDomain($this->commandBus->dispatch(new CloseParcel(
            UriVariable::string($uriVariables, 'id'),
            CloseParcelContents::from($data->contents),
            $data->weightGrams,
        )));
    }
}
