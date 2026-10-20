<?php

declare(strict_types=1);

namespace App\Identity\Application\RegisterUser;

use App\Identity\Domain\User;
use App\Shared\Application\Bus\Command;

/**
 * @implements Command<User>
 */
final readonly class RegisterUser implements Command
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $tenantId,
        public string $email,
        #[\SensitiveParameter]
        public string $password,
        public array $roles,
    ) {
    }
}
