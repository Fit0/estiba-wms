<?php

declare(strict_types=1);

namespace App\Identity\Application\RegisterUser;

use App\Identity\Domain\Email;
use App\Identity\Domain\Exception\TenantNotFound;
use App\Identity\Domain\PasswordHasher;
use App\Identity\Domain\PlainPassword;
use App\Identity\Domain\Role;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantRepository;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\TenantId;

/**
 * The command bus owns the transaction and flushes.
 */
final readonly class RegisterUserHandler implements CommandHandler
{
    public function __construct(
        private TenantRepository $tenants,
        private UserRepository $users,
        private PasswordHasher $hasher,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    /**
     * @throws TenantNotFound
     */
    public function __invoke(RegisterUser $command): User
    {
        $tenantId = TenantId::fromString($command->tenantId);
        if (!$this->tenants->find($tenantId) instanceof Tenant) {
            throw TenantNotFound::forId($command->tenantId);
        }

        $user = User::register(
            UserId::fromString($this->ids->next()),
            $tenantId,
            Email::fromString($command->email),
            $this->hasher->hash(PlainPassword::fromString($command->password)),
            array_map(Role::fromString(...), $command->roles),
            $this->users,
            $this->clock->now(),
        );

        $this->users->save($user);

        return $user;
    }
}
