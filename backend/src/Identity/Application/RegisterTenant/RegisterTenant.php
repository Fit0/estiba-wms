<?php

declare(strict_types=1);

namespace App\Identity\Application\RegisterTenant;

use App\Shared\Application\Bus\Command;

/**
 * Creates a tenant together with its first administrator, who then creates everyone else.
 *
 * @implements Command<RegisteredTenant>
 */
final readonly class RegisterTenant implements Command
{
    public function __construct(
        public string $name,
        public string $language,
        public string $adminEmail,
        #[\SensitiveParameter]
        public string $adminPassword,
    ) {
    }
}
