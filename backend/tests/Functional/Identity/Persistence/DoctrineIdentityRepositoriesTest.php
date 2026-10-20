<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Persistence;

use App\Identity\Domain\Email;
use App\Identity\Domain\PasswordHash;
use App\Identity\Domain\Role;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantName;
use App\Identity\Domain\User;
use App\Identity\Domain\UserStatus;
use App\Identity\Infrastructure\Persistence\Doctrine\DoctrineTenantRepository;
use App\Identity\Infrastructure\Persistence\Doctrine\DoctrineUserRepository;
use App\Shared\Domain\Language;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Identity\Domain\IdentityMother;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineIdentityRepositoriesTest extends PersistenceTestCase
{
    private DoctrineUserRepository $users;

    private DoctrineTenantRepository $tenants;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->users = new DoctrineUserRepository($this->entityManager);
        $this->tenants = new DoctrineTenantRepository($this->entityManager);
    }

    public function testFindsASavedTenant(): void
    {
        $tenant = Tenant::register(IdentityMother::tenantId(), TenantName::fromString('Acme Logistics'), Language::English, IdentityMother::at());
        $this->tenants->save($tenant);
        $this->flushAndForget();

        $found = $this->tenants->find($tenant->id());

        self::assertNotNull($found);
        self::assertSame('Acme Logistics', $found->name()->value);
        self::assertSame(Language::English, $found->defaultLanguage());
    }

    public function testFindsASavedUserWithItsRolesAndStatus(): void
    {
        $user = IdentityMother::user('Ana@Example.com', [Role::Supervisor, Role::Operator]);
        $user->disable(IdentityMother::at());
        $this->users->save($user);
        $this->flushAndForget();

        $found = $this->users->find($user->id());

        self::assertNotNull($found);
        self::assertSame('ana@example.com', $found->email()->value);
        self::assertTrue($found->tenantId()->equals($user->tenantId()));
        self::assertSame([Role::Supervisor, Role::Operator], $found->roles());
        self::assertSame(UserStatus::Disabled, $found->status());
        self::assertSame('$2y$04$hash-of-something', $found->passwordHash()->value);
    }

    public function testFindsAUserByEmailIgnoringCase(): void
    {
        $user = IdentityMother::user('ana@example.com');
        $this->users->save($user);
        $this->flushAndForget();

        self::assertNotNull($this->users->findByEmail(Email::fromString('ANA@Example.COM')));
        self::assertNull($this->users->findByEmail(Email::fromString('nobody@example.com')));
    }

    public function testKnowsWhichEmailsAreTaken(): void
    {
        $this->users->save(IdentityMother::user('ana@example.com'));
        $this->flushAndForget();

        self::assertTrue($this->users->isTaken(Email::fromString('ANA@example.com')));
        self::assertFalse($this->users->isTaken(Email::fromString('luis@example.com')));
    }

    // IDN-01: the database backs the check against two requests racing each other.
    public function testTheDatabaseRefusesTwoUsersWithTheSameEmail(): void
    {
        $this->users->save(IdentityMother::user('ana@example.com'));
        $this->users->save(IdentityMother::user('ana@example.com'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testAPasswordHashIsStoredAsGiven(): void
    {
        $user = User::register(
            IdentityMother::userId(),
            IdentityMother::tenantId(),
            Email::fromString('ana@example.com'),
            new PasswordHash('$argon2id$v=19$m=65536,t=4,p=1$abc$def'),
            [Role::Admin],
            $this->users,
            IdentityMother::at(),
        );
        $this->users->save($user);
        $this->flushAndForget();

        self::assertSame('$argon2id$v=19$m=65536,t=4,p=1$abc$def', $this->users->find($user->id())?->passwordHash()->value);
    }
}
