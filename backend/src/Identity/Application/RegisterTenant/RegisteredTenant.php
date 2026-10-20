<?php

declare(strict_types=1);

namespace App\Identity\Application\RegisterTenant;

use App\Identity\Domain\Tenant;
use App\Identity\Domain\User;

final readonly class RegisteredTenant
{
    public function __construct(
        public Tenant $tenant,
        public User $admin,
    ) {
    }
}
