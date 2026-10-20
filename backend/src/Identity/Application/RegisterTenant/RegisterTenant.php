<?php

declare(strict_types=1);

namespace App\Identity\Application\RegisterTenant;

/**
 * Creates a tenant together with its first administrator, who then creates everyone else.
 */
final readonly class RegisterTenant
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
