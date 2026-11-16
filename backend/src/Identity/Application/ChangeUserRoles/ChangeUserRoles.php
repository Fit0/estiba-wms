<?php

declare(strict_types=1);

namespace App\Identity\Application\ChangeUserRoles;

use App\Identity\Domain\User;
use App\Shared\Application\Bus\Command;

/**
 * @implements Command<User>
 */
final readonly class ChangeUserRoles implements Command
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $userId,
        public array $roles,
    ) {
    }
}
