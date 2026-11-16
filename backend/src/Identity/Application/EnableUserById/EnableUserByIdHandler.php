<?php

declare(strict_types=1);

namespace App\Identity\Application\EnableUserById;

use App\Identity\Application\TenantUsers;
use App\Identity\Domain\Exception\UserNotDisabled;
use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;

/**
 * The command bus owns the transaction and flushes.
 */
final readonly class EnableUserByIdHandler implements CommandHandler
{
    public function __construct(
        private TenantUsers $tenantUsers,
        private UserRepository $users,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    /**
     * @throws UserNotFound
     * @throws UserNotDisabled
     */
    public function __invoke(EnableUserById $command): User
    {
        $user = $this->tenantUsers->get($command->userId);
        $user->enable($this->clock->now());
        $this->users->save($user);

        $this->events->publish(...$user->pullEvents());

        return $user;
    }
}
