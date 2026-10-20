<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Email;
use App\Identity\Domain\PasswordHash;
use App\Identity\Domain\Role;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\Ids;

/**
 * Builds valid objects so each test only spells out what it is about.
 */
final class IdentityMother
{
    public static function at(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-20T10:00:00+00:00');
    }

    public static function userId(): UserId
    {
        return UserId::fromString(Ids::next());
    }

    public static function tenantId(): TenantId
    {
        return TenantId::fromString(Ids::next());
    }

    /**
     * @param list<Role> $roles
     */
    public static function user(string $email = 'ana@example.com', array $roles = [Role::Supervisor], ?TenantId $tenantId = null): User
    {
        return User::register(
            self::userId(),
            $tenantId ?? self::tenantId(),
            Email::fromString($email),
            new PasswordHash('$2y$04$hash-of-something'),
            $roles,
            new InMemoryEmails(),
            self::at(),
        );
    }
}
