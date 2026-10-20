<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\DisableUser\DisableUser;
use App\Identity\Application\DisableUser\DisableUserHandler;
use App\Identity\Application\EnableUser\EnableUser;
use App\Identity\Application\EnableUser\EnableUserHandler;
use App\Identity\Application\RegisterTenant\RegisteredTenant;
use App\Identity\Application\RegisterTenant\RegisterTenant;
use App\Identity\Application\RegisterTenant\RegisterTenantHandler;
use App\Identity\Application\RegisterUser\RegisterUser;
use App\Identity\Application\RegisterUser\RegisterUserHandler;
use App\Identity\Domain\Role;
use App\Identity\Domain\User;
use App\Shared\Domain\Language;
use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

final class IdentityUseCasesTest extends TestCase
{
    use AssertsDomainErrors;

    private IdentityFakes $fakes;

    #[\Override]
    protected function setUp(): void
    {
        $this->fakes = new IdentityFakes();
    }

    public function testRegistersATenantTogetherWithItsFirstAdministrator(): void
    {
        $registered = $this->registerTenant()(new RegisterTenant(' Acme Logistics ', 'EN', 'Admin@Acme.test', 'correct-horse-battery'));

        self::assertSame('Acme Logistics', $registered->tenant->name()->value);
        self::assertSame(Language::English, $registered->tenant->defaultLanguage());
        self::assertSame('admin@acme.test', $registered->admin->email()->value);
        self::assertSame([Role::Admin], $registered->admin->roles());
        self::assertTrue($registered->admin->tenantId()->equals($registered->tenant->id()));

        self::assertArrayHasKey($registered->tenant->id()->value, $this->fakes->tenants->saved);
        self::assertArrayHasKey('admin@acme.test', $this->fakes->users->saved);
    }

    public function testTheAdministratorPasswordIsHashedBeforeItIsKept(): void
    {
        $registered = $this->registerTenant()(new RegisterTenant('Acme', 'es', 'admin@acme.test', 'correct-horse-battery'));

        self::assertSame('hashed:'.strrev('correct-horse-battery'), $registered->admin->passwordHash()->value);
        self::assertStringNotContainsString('correct-horse-battery', $registered->admin->passwordHash()->value);
    }

    public function testRejectsALanguageThatIsNotSpoken(): void
    {
        self::assertDomainError(
            'identity.language_invalid',
            ['value' => 'fr'],
            fn (): RegisteredTenant => $this->registerTenant()(new RegisterTenant('Acme', 'fr', 'admin@acme.test', 'correct-horse-battery')),
        );
        self::assertSame([], $this->fakes->tenants->saved, 'Nothing is kept when the command is refused.');
    }

    public function testRejectsAWeakAdministratorPasswordAndKeepsNothing(): void
    {
        self::assertDomainError(
            'identity.password_invalid',
            ['minLength' => 12, 'maxLength' => 72],
            fn (): RegisteredTenant => $this->registerTenant()(new RegisterTenant('Acme', 'es', 'admin@acme.test', 'short')),
        );
        self::assertSame([], $this->fakes->users->saved);
    }

    public function testRegistersAUserInAnExistingTenant(): void
    {
        $tenant = $this->registerTenant()(new RegisterTenant('Acme', 'es', 'admin@acme.test', 'correct-horse-battery'))->tenant;

        $user = $this->registerUser()(new RegisterUser($tenant->id()->value, 'Luis@Acme.test', 'correct-horse-battery', ['Supervisor', 'operator']));

        self::assertSame('luis@acme.test', $user->email()->value);
        self::assertSame([Role::Supervisor, Role::Operator], $user->roles());
        self::assertTrue($user->tenantId()->equals($tenant->id()));
    }

    public function testRejectsAUserForATenantThatDoesNotExist(): void
    {
        $missing = Ids::next();

        self::assertDomainError(
            'identity.tenant_not_found',
            ['id' => $missing],
            fn (): User => $this->registerUser()(new RegisterUser($missing, 'luis@acme.test', 'correct-horse-battery', ['operator'])),
        );
    }

    public function testRejectsAnUnknownRoleAndAUserWithoutRoles(): void
    {
        $tenant = $this->registerTenant()(new RegisterTenant('Acme', 'es', 'admin@acme.test', 'correct-horse-battery'))->tenant;

        self::assertDomainError('identity.role_invalid', ['value' => 'owner'], fn (): User => $this->registerUser()(new RegisterUser($tenant->id()->value, 'a@acme.test', 'correct-horse-battery', ['owner'])));
        self::assertDomainError('identity.roles_required', [], fn (): User => $this->registerUser()(new RegisterUser($tenant->id()->value, 'b@acme.test', 'correct-horse-battery', [])));
    }

    public function testRejectsAnEmailThatAnotherTenantAlreadyUses(): void
    {
        $this->registerTenant()(new RegisterTenant('Acme', 'es', 'admin@acme.test', 'correct-horse-battery'));
        $rival = $this->registerTenant()(new RegisterTenant('Rival', 'es', 'admin@rival.test', 'correct-horse-battery'))->tenant;

        self::assertDomainError(
            'identity.email_already_used',
            ['email' => 'admin@acme.test'],
            fn (): User => $this->registerUser()(new RegisterUser($rival->id()->value, 'admin@acme.test', 'correct-horse-battery', ['operator'])),
        );
    }

    public function testDisablesAndEnablesAUser(): void
    {
        $this->registerTenant()(new RegisterTenant('Acme', 'es', 'admin@acme.test', 'correct-horse-battery'));
        $disable = new DisableUserHandler($this->fakes->users, IdentityFakes::clock());
        $enable = new EnableUserHandler($this->fakes->users, IdentityFakes::clock());

        self::assertFalse($disable(new DisableUser(' ADMIN@acme.test '))->isActive());
        self::assertTrue($enable(new EnableUser('admin@acme.test'))->isActive());
    }

    public function testReportsAUserThatDoesNotExist(): void
    {
        $disable = new DisableUserHandler($this->fakes->users, IdentityFakes::clock());

        self::assertDomainError('identity.user_not_found', ['email' => 'nobody@acme.test'], static fn (): User => $disable(new DisableUser('nobody@acme.test')));
    }

    private function registerTenant(): RegisterTenantHandler
    {
        return new RegisterTenantHandler($this->fakes->tenants, $this->fakes->users, IdentityFakes::hasher(), IdentityFakes::ids(), IdentityFakes::clock());
    }

    private function registerUser(): RegisterUserHandler
    {
        return new RegisterUserHandler($this->fakes->tenants, $this->fakes->users, IdentityFakes::hasher(), IdentityFakes::ids(), IdentityFakes::clock());
    }
}
