<?php

declare(strict_types=1);

namespace App\Identity\Application\DisableUserById;

use App\Identity\Domain\User;
use App\Shared\Application\Bus\Command;

/**
 * Disables a user of the current tenant, on behalf of the signed-in administrator. The console command
 * {@see \App\Identity\Application\DisableUser\DisableUser} does the same by email address, with nobody signed in.
 *
 * @implements Command<User>
 */
final readonly class DisableUserById implements Command
{
    public function __construct(public string $userId)
    {
    }
}
