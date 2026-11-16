<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\EnableUserById\EnableUserById;
use App\Identity\Infrastructure\Http\Resource\UserResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<null, UserResource>
 */
final readonly class EnableUserProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserResource
    {
        return UserResource::fromDomain($this->commandBus->dispatch(new EnableUserById(UriVariable::string($uriVariables, 'id'))));
    }
}
