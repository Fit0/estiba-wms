<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Inbound\Application\SplitPutawayTask\SplitPutawayTask;
use App\Inbound\Infrastructure\Http\Input\SplitPutawayTaskInput;
use App\Inbound\Infrastructure\Http\Resource\PutawayTaskResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * Answers with the new task.
 *
 * @implements ProcessorInterface<SplitPutawayTaskInput, PutawayTaskResource>
 */
final readonly class SplitPutawayTaskProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PutawayTaskResource
    {
        return PutawayTaskResource::fromDomain($this->commandBus->dispatch(new SplitPutawayTask(
            UriVariable::string($uriVariables, 'id'),
            $data->units,
        )));
    }
}
