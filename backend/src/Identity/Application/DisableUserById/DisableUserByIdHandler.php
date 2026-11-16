<?php

declare(strict_types=1);

namespace App\Identity\Application\DisableUserById;

use App\Identity\Application\TenantUsers;
use App\Identity\Domain\Exception\CannotChangeOwnAccess;
use App\Identity\Domain\Exception\LastAdminRequired;
use App\Identity\Domain\Exception\UserAlreadyDisabled;
use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentUser;

/**
 * The command bus owns the transaction and flushes.
 */
final readonly class DisableUserByIdHandler implements CommandHandler
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
     * @throws UserAlreadyDisabled
     * @throws LastAdminRequired     IDN-11
     */
    public function __invoke(DisableUserById $command): User
    {
        $user = $this->tenantUsers->get($command->userId);

        if ($user->id()->value === $this->current->id()) {
            throw CannotChangeOwnAccess::create();
        }

        $user->disable($this->clock->now(), $this->users);
        $this->users->save($user);

        $this->events->publish(...$user->pullEvents());

        return $user;
    }
}
