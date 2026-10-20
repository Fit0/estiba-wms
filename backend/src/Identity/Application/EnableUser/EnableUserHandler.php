<?php

declare(strict_types=1);

namespace App\Identity\Application\EnableUser;

use App\Identity\Domain\Email;
use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;

/**
 * The command bus owns the transaction and flushes.
 */
final readonly class EnableUserHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private Clock $clock,
    ) {
    }

    /**
     * @throws UserNotFound
     */
    public function __invoke(EnableUser $command): User
    {
        $user = $this->users->findByEmail(Email::fromString($command->email)) ?? throw UserNotFound::forEmail($command->email);
        $user->enable($this->clock->now());
        $this->users->save($user);

        return $user;
    }
}
