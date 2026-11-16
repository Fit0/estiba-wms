<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\ChangeUserRoles\ChangeUserRoles;
use App\Identity\Infrastructure\Http\Input\ChangeUserRolesInput;
use App\Identity\Infrastructure\Http\Resource\UserResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<ChangeUserRolesInput, UserResource>
 */
final readonly class ChangeUserRolesProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserResource
    {
        return UserResource::fromDomain($this->commandBus->dispatch(new ChangeUserRoles(UriVariable::string($uriVariables, 'id'), $data->roles)));
    }
}
