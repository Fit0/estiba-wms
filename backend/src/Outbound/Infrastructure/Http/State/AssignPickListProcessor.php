<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Outbound\Application\AssignPickList\AssignPickList;
use App\Outbound\Infrastructure\Http\Input\AssignPickListInput;
use App\Outbound\Infrastructure\Http\Resource\PickListResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<AssignPickListInput, PickListResource>
 */
final readonly class AssignPickListProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PickListResource
    {
        return PickListResource::fromDomain($this->commandBus->dispatch(new AssignPickList(UriVariable::string($uriVariables, 'id'), $data->operatorId)));
    }
}
