<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Identity\Domain\Role;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\Http\State\MeProvider;

/**
 * Who the token belongs to: the user, its roles and its tenant. A client calls it right after
 * signing in to know what to show.
 */
#[ApiResource(
    shortName: 'Me',
    operations: [
        new Get(uriTemplate: '/me', provider: MeProvider::class),
    ],
)]
final readonly class MeResource
{
    /**
     * @param list<string> $roles the role names: `admin`, `supervisor` or `operator`
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $email,
        public array $roles,
        public TenantSummary $tenant,
    ) {
    }

    public static function fromDomain(User $user, Tenant $tenant): self
    {
        return new self(
            $user->id()->value,
            $user->email()->value,
            array_map(static fn (Role $role): string => $role->value, $user->roles()),
            TenantSummary::fromDomain($tenant),
        );
    }
}
