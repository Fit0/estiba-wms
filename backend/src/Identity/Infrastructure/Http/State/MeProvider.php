<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantRepository;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Identity\Infrastructure\Http\Resource\MeResource;
use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * @implements ProviderInterface<MeResource>
 */
final readonly class MeProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private UserRepository $users,
        private TenantRepository $tenants,
    ) {
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MeResource
    {
        $current = $this->security->getUser();
        if (!$current instanceof SecurityUser) {
            throw new AuthenticationCredentialsNotFoundException();
        }

        $user = $this->users->find($current->id);
        $tenant = $this->tenants->find($current->tenantId);
        if (!$user instanceof User || !$tenant instanceof Tenant) {
            throw new AuthenticationCredentialsNotFoundException();
        }

        return MeResource::fromDomain($user, $tenant);
    }
}
