<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Email;
use App\Identity\Domain\Event\UserDisabled;
use App\Identity\Domain\Event\UserEnabled;
use App\Identity\Domain\Event\UserRegistered;
use App\Identity\Domain\PasswordHash;
use App\Identity\Domain\Role;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Identity\Domain\UserStatus;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    use AssertsDomainErrors;

    public function testRegistersAnActiveUserAndRaisesAnEvent(): void
    {
        $tenantId = IdentityMother::tenantId();

        $user = IdentityMother::user('Ana@Example.com', [Role::Supervisor], $tenantId);

        self::assertSame('ana@example.com', $user->email()->value);
        self::assertTrue($user->tenantId()->equals($tenantId));
        self::assertSame(UserStatus::Active, $user->status());
        self::assertTrue($user->isActive());
        self::assertTrue($user->hasRole(Role::Supervisor));
        self::assertFalse($user->hasRole(Role::Admin));

        $events = $user->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserRegistered::class, $events[0]);
        self::assertSame('ana@example.com', $events[0]->email->value);
    }

    // IDN-01: an email address belongs to one user in the whole system.
    public function testRejectsAnEmailThatAnotherUserAlreadyUses(): void
    {
        $registry = new InMemoryEmails()->take(Email::fromString('ana@example.com'));

        self::assertDomainError(
            'identity.email_already_used',
            ['email' => 'ana@example.com'],
            static fn (): User => User::register(
                IdentityMother::userId(),
                IdentityMother::tenantId(),
                Email::fromString('ANA@example.com'),
                new PasswordHash('hash'),
                [Role::Operator],
                $registry,
                IdentityMother::at(),
            ),
        );
    }

    // IDN-04: a user has at least one role.
    public function testRejectsAUserWithoutRoles(): void
    {
        self::assertDomainError('identity.roles_required', [], static fn (): User => IdentityMother::user(roles: []));
    }

    public function testAskingForTheSameRoleTwiceKeepsItOnce(): void
    {
        $user = IdentityMother::user(roles: [Role::Operator, Role::Operator, Role::Supervisor]);

        self::assertSame([Role::Operator, Role::Supervisor], $user->roles());
    }

    // IDN-06: a disabled user cannot sign in, nor use a token it already holds.
    public function testADisabledUserIsNotActive(): void
    {
        $user = IdentityMother::user();
        $user->pullEvents();

        $user->disable(IdentityMother::at());

        self::assertSame(UserStatus::Disabled, $user->status());
        self::assertFalse($user->isActive());
        self::assertInstanceOf(UserDisabled::class, $user->pullEvents()[0]);
    }

    public function testDisablingAUserThatIsAlreadyDisabledIsRejected(): void
    {
        $user = IdentityMother::user('ana@example.com');
        $user->disable(IdentityMother::at());
        $user->pullEvents();

        self::assertDomainError('identity.user_already_disabled', ['email' => 'ana@example.com'], static fn () => $user->disable(IdentityMother::at()));
        self::assertSame([], $user->pullEvents());
    }

    public function testEnablingBringsTheUserBack(): void
    {
        $user = IdentityMother::user();
        $user->disable(IdentityMother::at());
        $user->pullEvents();

        $user->enable(IdentityMother::at());

        self::assertTrue($user->isActive());
        self::assertInstanceOf(UserEnabled::class, $user->pullEvents()[0]);
    }

    public function testEnablingAUserThatIsNotDisabledIsRejected(): void
    {
        $user = IdentityMother::user('ana@example.com');

        self::assertDomainError('identity.user_not_disabled', ['email' => 'ana@example.com'], static fn () => $user->enable(IdentityMother::at()));
    }

    // IDN-05: a user belongs to one tenant for its whole life, so nothing can move it.
    public function testNothingCanChangeTheTenantOfAUser(): void
    {
        foreach ([User::class, UserRepository::class] as $type) {
            foreach (new \ReflectionClass($type)->getMethods() as $method) {
                self::assertDoesNotMatchRegularExpression(
                    '/(changeTenant|moveTo|setTenant|assignTenant|transfer)/i',
                    $method->getName(),
                    \sprintf('%s::%s() would let a user change tenant (IDN-05).', $type, $method->getName()),
                );
            }
        }
    }
}
