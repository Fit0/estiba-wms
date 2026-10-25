<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Api;

use App\Identity\Application\DisableUser\DisableUser;
use App\Identity\Application\DisableUser\DisableUserHandler;
use App\Identity\Application\RegisterTenant\RegisteredTenant;
use App\Identity\Application\RegisterTenant\RegisterTenant;
use App\Identity\Application\RegisterTenant\RegisterTenantHandler;
use App\Identity\Application\RegisterUser\RegisterUser;
use App\Identity\Application\RegisterUser\RegisterUserHandler;
use App\Tests\Functional\Support\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Signing in, the token, and what each role may do (ADR 0008).
 */
final class AuthApiTest extends ApiTestCase
{
    private const string PASSWORD = 'correct-horse-battery';

    private RegisteredTenant $acme;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = $this->handler(RegisterTenantHandler::class)(new RegisterTenant('Acme Logistics', 'es', 'admin@acme.test', self::PASSWORD));
        $this->handler(RegisterUserHandler::class)(new RegisterUser($this->acme->tenant->id()->value, 'sup@acme.test', self::PASSWORD, ['supervisor']));
        $this->handler(RegisterUserHandler::class)(new RegisterUser($this->acme->tenant->id()->value, 'op@acme.test', self::PASSWORD, ['operator']));
        $this->entityManager()->flush();
    }

    public function testSignsInAndReturnsAToken(): void
    {
        $response = $this->signIn('admin@acme.test', self::PASSWORD);

        $this->assertStatus(200);
        $token = $response['token'] ?? null;
        self::assertIsString($token);

        $claims = $this->claimsOf($token);
        self::assertSame('admin@acme.test', $claims['username']);
        self::assertSame(['ROLE_ADMIN'], $claims['roles']);
        self::assertSame($this->acme->tenant->id()->value, $claims['tenantId']);
        self::assertSame($this->acme->admin->id()->value, $claims['userId']);
        self::assertEqualsWithDelta(time() + 3600, $claims['exp'], 5, 'A token lasts one hour.');
    }

    public function testTheEmailIsNotCaseSensitive(): void
    {
        $this->signIn(' Admin@ACME.test ', self::PASSWORD);

        $this->assertStatus(200);
    }

    // The answer is the same for a wrong password, an unknown email and a disabled account, so it
    // does not tell a stranger which accounts exist.
    public function testEveryFailedSignInLooksTheSame(): void
    {
        $this->handler(RegisterUserHandler::class)(new RegisterUser($this->acme->tenant->id()->value, 'gone@acme.test', self::PASSWORD, ['operator']));
        $this->entityManager()->flush();
        $this->handler(DisableUserHandler::class)(new DisableUser('gone@acme.test'));
        $this->entityManager()->flush();

        $answers = [];
        foreach ([
            'a wrong password' => ['admin@acme.test', 'not-the-password-at-all'],
            'an unknown email' => ['nobody@acme.test', self::PASSWORD],
            'a disabled account' => ['gone@acme.test', self::PASSWORD],
        ] as $label => [$email, $password]) {
            $problem = $this->signIn($email, $password);

            $this->assertProblem(401, 'identity.credentials_invalid', $problem);
            $answers[$label] = $problem;
        }

        self::assertCount(1, array_unique(array_map(static fn (array $problem): string => json_encode($problem, \JSON_THROW_ON_ERROR), $answers)), 'The three answers must be identical.');
    }

    public function testExplainsAFailedSignInInTheRequestedLanguage(): void
    {
        $this->acceptLanguage = 'en';

        $problem = $this->signIn('admin@acme.test', 'not-the-password-at-all');

        self::assertSame('The email or the password is not correct.', $this->text($problem, 'detail'));
    }

    public function testRejectsASignInWithoutAPassword(): void
    {
        $problem = $this->request('POST', '/api/auth/login', ['email' => 'admin@acme.test'], '');

        $this->assertProblem(400, 'shared.request_malformed', $problem);
    }

    public function testShowsWhoTheTokenBelongsTo(): void
    {
        $me = $this->requestWithToken('GET', '/api/me', $this->tokenOf('sup@acme.test'));

        $this->assertStatus(200);
        self::assertSame('sup@acme.test', $me['email']);
        self::assertSame(['supervisor'], $me['roles']);
        self::assertSame(
            ['id' => $this->acme->tenant->id()->value, 'name' => 'Acme Logistics', 'defaultLanguage' => 'es'],
            $me['tenant'],
        );
    }

    public function testAnswersARequestWithoutATokenAsNotSignedIn(): void
    {
        $problem = $this->requestWithToken('GET', '/api/warehouses', null);

        $this->assertProblem(401, 'shared.unauthenticated', $problem);
        self::assertSame('Bearer', $this->client->getResponse()->headers->get('WWW-Authenticate'));
    }

    public function testRejectsATokenThatIsNotOne(): void
    {
        $problem = $this->requestWithToken('GET', '/api/warehouses', 'abc.def.ghi');

        $this->assertProblem(401, 'shared.token_invalid', $problem);
    }

    public function testRejectsATokenWhoseSignatureWasTamperedWith(): void
    {
        $token = $this->tokenOf('op@acme.test');
        [$header, $payload, $signature] = explode('.', $token);
        $forged = $header.'.'.$payload.'.'.strrev($signature);

        $problem = $this->requestWithToken('GET', '/api/warehouses', $forged);

        $this->assertProblem(401, 'shared.token_invalid', $problem);
    }

    public function testAnExpiredTokenAsksForANewSignIn(): void
    {
        $expired = $this->handler(JWTEncoderInterface::class)->encode(['username' => 'op@acme.test', 'roles' => ['ROLE_OPERATOR'], 'iat' => time() - 7200, 'exp' => time() - 3600]);

        $problem = $this->requestWithToken('GET', '/api/warehouses', $expired);

        $this->assertProblem(401, 'shared.token_expired', $problem);
    }

    // IDN-06: a token does not outlive the account. The user is loaded on every request.
    public function testADisabledUserLosesAccessAtOnce(): void
    {
        $token = $this->tokenOf('op@acme.test');
        $this->requestWithToken('GET', '/api/warehouses', $token);
        $this->assertStatus(200);

        $this->handler(DisableUserHandler::class)(new DisableUser('op@acme.test'));
        $this->entityManager()->flush();

        $problem = $this->requestWithToken('GET', '/api/warehouses', $token);
        $this->assertProblem(401, 'shared.token_invalid', $problem);
    }

    /**
     * What each role may do. Reading needs any signed-in user, changing needs a supervisor.
     */
    #[DataProvider('roleMatrix')]
    public function testEachRoleDoesWhatItMay(string $email, string $method, int $status, ?string $code): void
    {
        $body = 'POST' === $method ? ['code' => 'WH1', 'name' => 'Warehouse'] : null;

        $problem = $this->requestWithToken($method, '/api/warehouses', $this->tokenOf($email), $body);

        $this->assertStatus($status);
        if (null !== $code) {
            self::assertSame($code, $problem['code']);
        }
    }

    /**
     * @return iterable<string, array{string, string, int, string|null}>
     */
    public static function roleMatrix(): iterable
    {
        yield 'an operator reads' => ['op@acme.test', 'GET', 200, null];
        yield 'an operator cannot change' => ['op@acme.test', 'POST', 403, 'shared.forbidden'];
        yield 'a supervisor reads' => ['sup@acme.test', 'GET', 200, null];
        yield 'a supervisor changes' => ['sup@acme.test', 'POST', 201, null];
        yield 'an admin reads' => ['admin@acme.test', 'GET', 200, null];
        yield 'an admin changes' => ['admin@acme.test', 'POST', 201, null];
    }

    // The roles of a token are not believed: the server reads them from the database.
    public function testATokenCannotGrantItselfMoreRoles(): void
    {
        $forged = $this->handler(JWTEncoderInterface::class)->encode([
            'username' => 'op@acme.test',
            'roles' => ['ROLE_ADMIN'],
            'tenantId' => $this->acme->tenant->id()->value,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $problem = $this->requestWithToken('POST', '/api/warehouses', $forged, ['code' => 'WH1', 'name' => 'Sneaky']);

        $this->assertProblem(403, 'shared.forbidden', $problem);
    }

    // The tenant of a request is the one of the signed-in user, whatever the request says.
    public function testTheTenantHeaderOfThePastIsIgnored(): void
    {
        $this->requestWithToken('POST', '/api/warehouses', $this->tokenOf('sup@acme.test'), ['code' => 'MAD1', 'name' => 'Madrid']);
        $this->assertStatus(201);

        $this->handler(RegisterTenantHandler::class)(new RegisterTenant('Rival', 'en', 'admin@rival.test', self::PASSWORD));
        $this->entityManager()->flush();

        $this->client->request('GET', '/api/warehouses', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenOf('admin@rival.test'),
            'HTTP_X_TENANT_ID' => $this->acme->tenant->id()->value,
        ]);

        $this->assertStatus(200);
        self::assertSame([], $this->decodedResponse(), 'Another tenant cannot borrow Acme through a header.');
    }

    // The tenant filter is the second wall behind the use cases, so it must be on for a signed-in
    // request and off for an anonymous one.
    public function testTheTenantFilterFollowsTheSignedInUser(): void
    {
        $this->requestWithToken('GET', '/api/warehouses', $this->tokenOf('op@acme.test'));
        $this->assertStatus(200);
        self::assertTrue($this->entityManager()->getFilters()->isEnabled('tenant'), 'A signed-in request must run with the tenant filter on.');

        $this->requestWithToken('GET', '/api/warehouses', null);
        $this->assertStatus(401);
        self::assertFalse($this->entityManager()->getFilters()->isEnabled('tenant'), 'An anonymous request must not carry the previous tenant.');
    }

    // IDN-08: signing in also starts a session, whose secret JavaScript can never read.
    public function testSigningInSetsTheRefreshTokenInACookieOfItsOwn(): void
    {
        $this->signIn('op@acme.test', self::PASSWORD);

        $cookie = $this->refreshCookie();
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly(), 'JavaScript must not read it.');
        self::assertSame('strict', $cookie->getSameSite());
        self::assertSame('/api/auth', $cookie->getPath(), 'The browser sends it to the sign-in routes only.');
        self::assertGreaterThan(40, \strlen($cookie->getValue()));
        self::assertEqualsWithDelta(time() + 7 * 86400, $cookie->getExpiresTime(), 5);
    }

    public function testASecretIsNeverInTheBodyOfTheAnswer(): void
    {
        $answer = $this->signIn('op@acme.test', self::PASSWORD);

        self::assertSame(['token'], array_keys($answer));
    }

    public function testRenewsTheSessionAndRotatesTheCookie(): void
    {
        $this->signIn('sup@acme.test', self::PASSWORD);
        $before = $this->refreshCookie()?->getValue();

        $answer = $this->refresh();

        $this->assertStatus(200);
        $token = $answer['token'] ?? null;
        self::assertIsString($token);
        self::assertSame('sup@acme.test', $this->claimsOf($token)['username']);
        self::assertNotSame($before, $this->refreshCookie()?->getValue(), 'Every secret works once.');
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $me = $this->requestWithToken('GET', '/api/me', $token);
        $this->assertStatus(200);
        self::assertSame('sup@acme.test', $me['email']);
    }

    public function testASessionCanBeRenewedAgainAndAgain(): void
    {
        $this->signIn('op@acme.test', self::PASSWORD);

        foreach ([1, 2, 3] as $_) {
            $this->refresh();
            $this->assertStatus(200);
        }
    }

    public function testASessionWithoutACookieCannotBeRenewed(): void
    {
        $problem = $this->refresh();

        $this->assertProblem(401, 'identity.refresh_token_invalid', $problem);
    }

    public function testASecretThatIsNotKnownCannotBeRenewedAndTheCookieIsCleared(): void
    {
        $this->client->getCookieJar()->set(new Cookie('estiba_refresh', 'made-up-secret', null, '/api/auth', 'localhost'));

        $problem = $this->refresh();

        $this->assertProblem(401, 'identity.refresh_token_invalid', $problem);
        $cleared = $this->responseCookie();
        self::assertNotNull($cleared);
        self::assertSame('', $cleared->getValue(), 'The browser is told to drop the cookie.');
        self::assertLessThan(time(), $cleared->getExpiresTime());
    }

    public function testExplainsAFailedRenewalInTheRequestedLanguage(): void
    {
        $this->acceptLanguage = 'en';

        $problem = $this->refresh();

        self::assertSame('The session cannot be renewed. Sign in again.', $this->text($problem, 'detail'));
    }

    // Two tabs ask at the same time with the same secret: the copy is refused, the session survives.
    public function testASecretThatWasJustSpentDoesNotEndTheSession(): void
    {
        $this->signIn('op@acme.test', self::PASSWORD);
        $spent = $this->refreshCookie();
        self::assertNotNull($spent);
        $this->refresh();
        $this->assertStatus(200);
        $newer = $this->refreshCookie();

        $this->client->getCookieJar()->set($spent);
        $this->refresh();
        $this->assertStatus(401);

        self::assertNotNull($newer);
        $this->client->getCookieJar()->set($newer);
        $this->refresh();
        $this->assertStatus(200);
    }

    // IDN-06: a disabled user cannot get a new token with the old session.
    public function testADisabledUserCannotRenewTheSession(): void
    {
        $this->signIn('op@acme.test', self::PASSWORD);
        $this->handler(DisableUserHandler::class)(new DisableUser('op@acme.test'));
        $this->entityManager()->flush();

        $problem = $this->refresh();

        $this->assertProblem(401, 'identity.refresh_token_invalid', $problem);
    }

    public function testASignedOutSessionCannotBeRenewed(): void
    {
        $this->signIn('op@acme.test', self::PASSWORD);
        $secret = $this->refreshCookie();
        self::assertNotNull($secret);

        $this->client->request('POST', '/api/auth/logout');
        $this->assertStatus(204);
        self::assertSame('', $this->responseCookie()?->getValue());

        $this->client->getCookieJar()->set($secret);
        $problem = $this->refresh();
        $this->assertProblem(401, 'identity.refresh_token_invalid', $problem);
    }

    public function testSigningOutWithoutASessionIsNotAnError(): void
    {
        $this->client->request('POST', '/api/auth/logout');

        $this->assertStatus(204);
    }

    // The routes of the session need no token, and a stale one that is sent along gets in no way.
    public function testRenewingIgnoresAnExpiredAccessToken(): void
    {
        $this->signIn('op@acme.test', self::PASSWORD);
        $expired = $this->handler(JWTEncoderInterface::class)->encode(['username' => 'op@acme.test', 'roles' => ['ROLE_OPERATOR'], 'iat' => time() - 7200, 'exp' => time() - 3600]);

        $answer = $this->refresh($expired);

        $this->assertStatus(200);
        self::assertIsString($answer['token'] ?? null);
    }

    public function testThePublicPartsNeedNoToken(): void
    {
        $paths = $this->openApiPaths();

        self::assertArrayHasKey('/api/auth/login', $paths, 'The login route is documented.');
        self::assertArrayHasKey('/api/me', $paths);

        $this->client->request('GET', '/health');
        $this->assertStatus(200);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function signIn(string $email, string $password): array
    {
        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => $this->acceptLanguage ?? '',
        ], json_encode(['email' => $email, 'password' => $password], \JSON_THROW_ON_ERROR));

        return $this->decodedResponse();
    }

    /**
     * @return array<array-key, mixed>
     */
    private function refresh(?string $staleToken = null): array
    {
        $headers = ['HTTP_ACCEPT' => 'application/json', 'HTTP_ACCEPT_LANGUAGE' => $this->acceptLanguage ?? ''];
        if (null !== $staleToken) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$staleToken;
        }

        $this->client->request('POST', '/api/auth/refresh', [], [], $headers);

        return $this->decodedResponse();
    }

    /**
     * The refresh token cookie that the browser holds.
     */
    private function refreshCookie(): ?Cookie
    {
        return $this->client->getCookieJar()->get('estiba_refresh', '/api/auth');
    }

    /**
     * The refresh token cookie of the last answer, as the server sent it.
     */
    private function responseCookie(): ?SymfonyCookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ('estiba_refresh' === $cookie->getName()) {
                return $cookie;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<array-key, mixed>
     */
    private function requestWithToken(string $method, string $uri, ?string $token, ?array $body = null): array
    {
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_ACCEPT_LANGUAGE' => $this->acceptLanguage ?? ''];
        if (null !== $token) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request($method, $uri, [], [], $headers, null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));

        return $this->decodedResponse();
    }

    /**
     * The token the server hands out when this user signs in with the right password.
     */
    private function tokenOf(string $email): string
    {
        $response = $this->signIn($email, self::PASSWORD);
        $token = $response['token'] ?? null;
        self::assertIsString($token, 'Signing in should return a token.');

        return $token;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function claimsOf(string $token): array
    {
        $payload = explode('.', $token)[1] ?? '';
        $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($claims));

        return $claims;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function handler(string $class): object
    {
        $service = self::getContainer()->get($class);
        \assert($service instanceof $class);

        return $service;
    }
}
