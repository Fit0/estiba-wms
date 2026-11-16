<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\CurrentTenant;

/**
 * Finds a user by id among the users of the current tenant. A user of another tenant is not found, the
 * same as one that does not exist: the tenant filter hides it, and this checks it again.
 */
final readonly class TenantUsers
{
    public function __construct(
        private UserRepository $users,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws UserNotFound
     */
    public function get(string $userId): User
    {
        $user = $this->users->find(UserId::fromString($userId));
        if (!$user instanceof User || !$user->tenantId()->equals($this->tenant->id())) {
            throw UserNotFound::forId($userId);
        }

        return $user;
    }
}
