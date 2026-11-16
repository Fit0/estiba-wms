<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Identity\Domain\Role;
use App\Identity\Domain\User;
use App\Identity\Domain\UserStatus;
use App\Identity\Infrastructure\Http\Input\ChangeUserRolesInput;
use App\Identity\Infrastructure\Http\Input\CreateUserInput;
use App\Identity\Infrastructure\Http\State\ChangeUserRolesProcessor;
use App\Identity\Infrastructure\Http\State\CreateUserProcessor;
use App\Identity\Infrastructure\Http\State\DisableUserProcessor;
use App\Identity\Infrastructure\Http\State\EnableUserProcessor;
use App\Identity\Infrastructure\Http\State\UserProvider;

/**
 * A user of the tenant, as the administrator sees it. The password is never part of it.
 */
#[ApiResource(
    shortName: 'User',
    operations: [
        new GetCollection(uriTemplate: '/users', provider: UserProvider::class),
        new Post(uriTemplate: '/users', input: CreateUserInput::class, processor: CreateUserProcessor::class),
        new Get(uriTemplate: '/users/{id}', provider: UserProvider::class),
        new Post(
            uriTemplate: '/users/{id}/roles',
            status: 200,
            input: ChangeUserRolesInput::class,
            read: false,
            processor: ChangeUserRolesProcessor::class,
        ),
        new Post(
            uriTemplate: '/users/{id}/disable',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: DisableUserProcessor::class,
        ),
        new Post(
            uriTemplate: '/users/{id}/enable',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: EnableUserProcessor::class,
        ),
    ],
)]
final readonly class UserResource
{
    /**
     * @param list<string> $roles the role names: `admin`, `supervisor` or `operator`
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $email,
        public array $roles,
        public UserStatus $status,
    ) {
    }

    public static function fromDomain(User $user): self
    {
        return new self(
            $user->id()->value,
            $user->email()->value,
            array_map(static fn (Role $role): string => $role->value, $user->roles()),
            $user->status(),
        );
    }
}
