<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;

interface UserRepository extends EmailRegistry, ActiveAdmins
{
    public function find(UserId $id): ?User;

    /**
     * Signing in happens before anyone is authenticated, so this looks across all tenants.
     */
    public function findByEmail(Email $email): ?User;

    public function save(User $user): void;

    /**
     * The users of the tenant, by email address.
     *
     * @return Page<User>
     */
    public function search(TenantId $tenantId, PageRequest $page): Page;
}
