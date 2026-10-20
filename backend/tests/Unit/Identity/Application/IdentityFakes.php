<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Domain\Email;
use App\Identity\Domain\PasswordHash;
use App\Identity\Domain\PasswordHasher;
use App\Identity\Domain\PlainPassword;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantRepository;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\Ids;

/**
 * The ports of the Identity use cases, in memory, so a unit test needs no database and no kernel.
 */
final readonly class IdentityFakes
{
    public InMemoryUsers $users;

    public InMemoryTenants $tenants;

    public function __construct()
    {
        $this->users = new InMemoryUsers();
        $this->tenants = new InMemoryTenants();
    }

    public static function hasher(): PasswordHasher
    {
        return new class implements PasswordHasher {
            public function hash(PlainPassword $password): PasswordHash
            {
                return new PasswordHash('hashed:'.strrev($password->reveal()));
            }
        };
    }

    public static function ids(): IdGenerator
    {
        return new class implements IdGenerator {
            #[\Override]
            public function next(): string
            {
                return Ids::next();
            }
        };
    }

    public static function clock(): Clock
    {
        return new class implements Clock {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-20T10:00:00+00:00');
            }
        };
    }
}

final class InMemoryUsers implements UserRepository
{
    /** @var array<string, User> by email */
    public array $saved = [];

    #[\Override]
    public function find(UserId $id): ?User
    {
        foreach ($this->saved as $user) {
            if ($user->id()->equals($id)) {
                return $user;
            }
        }

        return null;
    }

    #[\Override]
    public function findByEmail(Email $email): ?User
    {
        return $this->saved[$email->value] ?? null;
    }

    #[\Override]
    public function save(User $user): void
    {
        $this->saved[$user->email()->value] = $user;
    }

    #[\Override]
    public function isTaken(Email $email): bool
    {
        return isset($this->saved[$email->value]);
    }
}

final class InMemoryTenants implements TenantRepository
{
    /** @var array<string, Tenant> by identifier */
    public array $saved = [];

    #[\Override]
    public function find(TenantId $id): ?Tenant
    {
        return $this->saved[$id->value] ?? null;
    }

    #[\Override]
    public function save(Tenant $tenant): void
    {
        $this->saved[$tenant->id()->value] = $tenant;
    }
}
