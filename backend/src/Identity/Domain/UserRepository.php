<?php

declare(strict_types=1);

namespace App\Identity\Domain;

interface UserRepository extends EmailRegistry
{
    public function find(UserId $id): ?User;

    /**
     * Signing in happens before anyone is authenticated, so this looks across all tenants.
     */
    public function findByEmail(Email $email): ?User;

    public function save(User $user): void;
}
