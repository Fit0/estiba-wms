<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\RegisterUser\RegisterUser;
use App\Identity\Infrastructure\Http\Input\CreateUserInput;
use App\Identity\Infrastructure\Http\Resource\UserResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\CurrentTenant;

/**
 * The new user belongs to the tenant of the administrator who creates it, never to one named in the request.
 *
 * @implements ProcessorInterface<CreateUserInput, UserResource>
 */
final readonly class CreateUserProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private CurrentTenant $tenant,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserResource
    {
        return UserResource::fromDomain($this->commandBus->dispatch(new RegisterUser($this->tenant->id()->value, $data->email, $data->password, $data->roles)));
    }
}
