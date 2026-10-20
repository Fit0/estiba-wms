<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Console;

use App\Identity\Domain\Email;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Identity\Infrastructure\Console\PasswordPrompt;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

final class ConsoleCommandsTest extends PersistenceTestCase
{
    private const string PASSWORD = 'correct-horse-battery';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER[PasswordPrompt::ENVIRONMENT_VARIABLE] = self::PASSWORD;
    }

    #[\Override]
    protected function tearDown(): void
    {
        unset($_SERVER[PasswordPrompt::ENVIRONMENT_VARIABLE]);

        parent::tearDown();
    }

    public function testCreatesATenantWithItsAdministrator(): void
    {
        $tester = $this->command('app:tenant:create', ['name' => 'Acme Logistics', 'admin-email' => 'admin@acme.test', '--language' => 'en']);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Tenant created.', $tester->getDisplay());
        self::assertStringContainsString('admin@acme.test', $tester->getDisplay());
        self::assertStringNotContainsString(self::PASSWORD, $tester->getDisplay(), 'The password must never be printed.');

        $admin = $this->user('admin@acme.test');
        self::assertSame(['admin'], array_map(static fn ($role): string => $role->value, $admin->roles()));
        self::assertStringStartsWith('$', $admin->passwordHash()->value, 'The password is stored hashed.');
        self::assertStringNotContainsString(self::PASSWORD, $admin->passwordHash()->value);
    }

    public function testCreatesAUserInTheTenantWithTheRolesAsked(): void
    {
        $this->command('app:tenant:create', ['name' => 'Acme', 'admin-email' => 'admin@acme.test']);
        $tenantId = $this->user('admin@acme.test')->tenantId()->value;

        $tester = $this->command('app:user:create', ['tenant-id' => $tenantId, 'email' => 'sup@acme.test', '--role' => ['supervisor', 'operator']]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(['supervisor', 'operator'], array_map(static fn ($role): string => $role->value, $this->user('sup@acme.test')->roles()));
    }

    public function testAUserIsAnOperatorUnlessToldOtherwise(): void
    {
        $this->command('app:tenant:create', ['name' => 'Acme', 'admin-email' => 'admin@acme.test']);
        $tenantId = $this->user('admin@acme.test')->tenantId()->value;

        $this->command('app:user:create', ['tenant-id' => $tenantId, 'email' => 'op@acme.test']);

        self::assertSame(['operator'], array_map(static fn ($role): string => $role->value, $this->user('op@acme.test')->roles()));
    }

    public function testExplainsWhyACommandFailed(): void
    {
        $this->command('app:tenant:create', ['name' => 'Acme', 'admin-email' => 'admin@acme.test']);
        $tenantId = $this->user('admin@acme.test')->tenantId()->value;

        $duplicate = $this->command('app:user:create', ['tenant-id' => $tenantId, 'email' => 'ADMIN@acme.test']);
        self::assertSame(1, $duplicate->getStatusCode());
        self::assertStringContainsString('Another user already uses the email address admin@acme.test.', $duplicate->getDisplay());

        $badRole = $this->command('app:user:create', ['tenant-id' => $tenantId, 'email' => 'x@acme.test', '--role' => ['owner']]);
        self::assertSame(1, $badRole->getStatusCode());
        self::assertStringContainsString('"owner" is not a role', $badRole->getDisplay());

        $_SERVER[PasswordPrompt::ENVIRONMENT_VARIABLE] = 'short';
        $weak = $this->command('app:user:create', ['tenant-id' => $tenantId, 'email' => 'y@acme.test']);
        self::assertSame(1, $weak->getStatusCode());
        self::assertStringContainsString('A password must have 12 to 72 characters.', $weak->getDisplay());
        self::assertStringNotContainsString('short', str_replace('A password must have', '', $weak->getDisplay()), 'The weak password must not be echoed.');
    }

    public function testRefusesToRunWithoutAPasswordWhenItCannotAsk(): void
    {
        unset($_SERVER[PasswordPrompt::ENVIRONMENT_VARIABLE]);

        try {
            $this->command('app:tenant:create', ['name' => 'Acme', 'admin-email' => 'admin@acme.test'], interactive: false);
            self::fail('The command should refuse to run without a password.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString(PasswordPrompt::ENVIRONMENT_VARIABLE, $error->getMessage());
        }

        self::assertNull($this->users()->findByEmail(Email::fromString('admin@acme.test')), 'Nothing is created without a password.');
    }

    public function testDisablesAndEnablesAUser(): void
    {
        $this->command('app:tenant:create', ['name' => 'Acme', 'admin-email' => 'admin@acme.test']);

        self::assertSame(0, $this->command('app:user:disable', ['email' => 'admin@acme.test'])->getStatusCode());
        $this->entityManager->clear();
        self::assertFalse($this->user('admin@acme.test')->isActive());

        $again = $this->command('app:user:disable', ['email' => 'admin@acme.test']);
        self::assertSame(1, $again->getStatusCode());
        self::assertStringContainsString('already disabled', $again->getDisplay());

        self::assertSame(0, $this->command('app:user:enable', ['email' => 'admin@acme.test'])->getStatusCode());
        $this->entityManager->clear();
        self::assertTrue($this->user('admin@acme.test')->isActive());

        $unknown = $this->command('app:user:enable', ['email' => 'nobody@acme.test']);
        self::assertSame(1, $unknown->getStatusCode());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function command(string $name, array $input, bool $interactive = true): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new \LogicException('The kernel is not booted.'));
        $tester = new CommandTester($application->find($name));
        $tester->execute($input, ['interactive' => $interactive]);

        return $tester;
    }

    private function users(): UserRepository
    {
        return self::getContainer()->get(UserRepository::class);
    }

    private function user(string $email): User
    {
        $user = $this->users()->findByEmail(Email::fromString($email));
        self::assertNotNull($user, \sprintf('User %s should exist.', $email));

        return $user;
    }
}
