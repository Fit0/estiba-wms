<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\Input;

final readonly class CreateUserInput
{
    /**
     * @param list<string> $roles `admin`, `supervisor` or `operator`
     */
    public function __construct(
        public string $email,
        #[\SensitiveParameter]
        public string $password,
        public array $roles,
    ) {
    }
}
