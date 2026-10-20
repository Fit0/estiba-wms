<?php

declare(strict_types=1);

namespace App\Identity\Application\RegisterUser;

final readonly class RegisterUser
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
