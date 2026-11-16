<?php

declare(strict_types=1);

namespace App\Identity\Application\ChangeUserRoles;

use App\Identity\Application\TenantUsers;
use App\Identity\Domain\Exception\CannotChangeOwnAccess;
use App\Identity\Domain\Exception\LastAdminRequired;
use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\Exception\UserWithoutRole;
use App\Identity\Domain\Role;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentUser;

/**
 * The command bus owns the transaction and flushes.
 */
final readonly class ChangeUserRolesHandler implements CommandHandler
{
    public function __construct(
        private TenantUsers $tenantUsers,
        private UserRepository $users,
        private CurrentUser $current,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    /**
     * @throws UserNotFound
     * @throws CannotChangeOwnAccess IDN-12
     * @throws UserWithoutRole       IDN-04
     * @throws LastAdminRequired     IDN-11
     */
    public function __invoke(ChangeUserRoles $command): User
    {
        $user = $this->tenantUsers->get($command->userId);
        $roles = array_map(Role::fromString(...), $command->roles);

        // IDN-12: a user keeps its own admin role, so it cannot lock itself out by mistake.
        if ($user->id()->value === $this->current->id() && $user->hasRole(Role::Admin) && !\in_array(Role::Admin, $roles, true)) {
            throw CannotChangeOwnAccess::create();
        }

        $user->changeRoles($roles, $this->clock->now(), $this->users);
        $this->users->save($user);

        $this->events->publish(...$user->pullEvents());

        return $user;
    }
}
