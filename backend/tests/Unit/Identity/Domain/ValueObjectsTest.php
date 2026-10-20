<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Email;
use App\Identity\Domain\PlainPassword;
use App\Identity\Domain\Role;
use App\Identity\Domain\TenantName;
use App\Shared\Domain\DomainError;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    use AssertsDomainErrors;

    public function testEmailIsKeptInLowerCaseWithoutSurroundingSpaces(): void
    {
        self::assertSame('ana.garcia@example.com', Email::fromString('  Ana.Garcia@Example.COM ')->value);
        self::assertTrue(Email::fromString('ANA@example.com')->equals(Email::fromString('ana@EXAMPLE.com')));
    }

    // IDN-02: an email address must be valid.
    #[DataProvider('invalidEmails')]
    public function testRejectsAnEmailThatIsNotValid(string $value): void
    {
        self::assertDomainError('identity.email_invalid', ['value' => $value], static fn (): Email => Email::fromString($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'no at sign' => ['ana.example.com'];
        yield 'no domain' => ['ana@'];
        yield 'no name' => ['@example.com'];
        yield 'spaces inside' => ['ana garcia@example.com'];
        yield 'two at signs' => ['ana@@example.com'];
        yield 'too long' => [str_repeat('a', 250).'@example.com'];
    }

    // IDN-03: a password has 12 to 72 characters, and 72 is a limit in bytes.
    public function testAcceptsAPasswordWithinTheLimits(): void
    {
        self::assertSame('correct horse', PlainPassword::fromString('correct horse')->reveal());
        self::assertSame(str_repeat('a', 72), PlainPassword::fromString(str_repeat('a', 72))->reveal());
        self::assertSame(str_repeat('ñ', 12), PlainPassword::fromString(str_repeat('ñ', 12))->reveal(), 'Characters are counted for the minimum, not bytes.');
    }

    #[DataProvider('invalidPasswords')]
    public function testRejectsAPasswordOutsideTheLimits(string $password): void
    {
        self::assertDomainError(
            'identity.password_invalid',
            ['minLength' => 12, 'maxLength' => 72],
            static fn (): PlainPassword => PlainPassword::fromString($password),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPasswords(): iterable
    {
        yield 'empty' => [''];
        yield 'one short of the minimum' => [str_repeat('a', 11)];
        yield 'one over the limit in bytes' => [str_repeat('a', 73)];
        yield 'short in characters but not in bytes' => [str_repeat('ñ', 11)];
        yield 'long in bytes' => [str_repeat('ñ', 37)];
    }

    public function testAPasswordNeverReachesAnErrorOrALog(): void
    {
        $secret = 'my-very-secret-password';
        $password = PlainPassword::fromString($secret);

        self::assertStringNotContainsString($secret, print_r($password, true));
        self::assertStringNotContainsString($secret, var_export(json_encode($password), true));

        try {
            PlainPassword::fromString('short');
            self::fail('A short password should be rejected.');
        } catch (\Throwable $error) {
            self::assertStringNotContainsString('short', $error->getMessage());
            self::assertStringNotContainsString('short', (string) json_encode($error instanceof DomainError ? $error->errorParameters() : []));
        }
    }

    // IDN-07: a tenant name has 1 to 100 characters and no control characters.
    public function testTenantNameIsTrimmed(): void
    {
        self::assertSame('Distribuciones Norte', TenantName::fromString('  Distribuciones Norte ')->value);
        self::assertSame(str_repeat('ñ', 100), TenantName::fromString(str_repeat('ñ', 100))->value);
    }

    public function testRejectsAnInvalidTenantName(): void
    {
        foreach (['', '   ', str_repeat('a', 101), "Two\nlines"] as $value) {
            self::assertDomainError('identity.tenant_name_invalid', ['maxLength' => 100], static fn (): TenantName => TenantName::fromString($value));
        }
    }

    public function testParsesARoleIgnoringCase(): void
    {
        self::assertSame(Role::Admin, Role::fromString(' ADMIN '));
        self::assertSame(Role::Operator, Role::fromString('operator'));
    }

    public function testRejectsAnUnknownRole(): void
    {
        self::assertDomainError('identity.role_invalid', ['value' => 'owner'], static fn (): Role => Role::fromString('owner'));
    }
}
