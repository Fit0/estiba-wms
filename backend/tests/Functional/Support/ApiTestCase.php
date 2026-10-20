<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use App\Identity\Domain\Email;
use App\Identity\Domain\PasswordHash;
use App\Identity\Domain\Role;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantName;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Identity\Infrastructure\Persistence\Doctrine\DoctrineUserRepository;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\Language;
use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\Ids;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base of the API tests: a client, an empty database built from the mapping, and a signed-in user.
 * Requests send and receive JSON and carry a real JWT.
 *
 * By default the requests act as an admin of {@see $tenant}. Pass another tenant to a request to
 * act as an admin of that one, set {@see $role} to act with fewer rights, or pass an empty tenant
 * to send no token at all.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected string $tenant;

    /**
     * The role of the user the requests act as.
     */
    protected Role $role = Role::Admin;

    /**
     * The `Accept-Language` the requests send. Null sends an empty one, because the test client would
     * otherwise add `en-us` on its own. The API answers an empty header in Spanish.
     */
    protected ?string $acceptLanguage = null;

    /** @var array<string, string> the token of each tenant and role, made on first use */
    private array $tokens = [];

    /** @var array<string, true> the tenants already created in this test */
    private array $tenants = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->tenant = Ids::next();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, int|string> $query
     *
     * @return array<array-key, mixed>
     */
    protected function request(string $method, string $uri, ?array $body = null, ?string $tenant = null, array $query = []): array
    {
        $contentType = 'PATCH' === $method ? 'application/merge-patch+json' : 'application/json';
        $headers = ['CONTENT_TYPE' => $contentType, 'HTTP_ACCEPT' => 'application/json'];
        $headers['HTTP_ACCEPT_LANGUAGE'] = $this->acceptLanguage ?? '';
        $tenant ??= $this->tenant;
        if ('' !== $tenant) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$this->tokenFor($tenant, $this->role);
        }

        $this->client->request($method, $uri, $query, [], $headers, null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));

        return $this->decodedResponse();
    }

    /**
     * A token for a user of the tenant with the role. The tenant and the user are created in the
     * database the first time they are needed, the way a real sign-in would find them.
     */
    protected function tokenFor(string $tenantId, Role $role): string
    {
        $key = $tenantId.'|'.$role->value;
        if (isset($this->tokens[$key])) {
            return $this->tokens[$key];
        }

        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $at = new \DateTimeImmutable('2026-10-20T10:00:00+00:00');
        $tenant = TenantId::fromString($tenantId);

        if (!isset($this->tenants[$tenantId])) {
            $entityManager->persist(Tenant::register($tenant, TenantName::fromString('Tenant '.substr($tenantId, -4)), Language::Spanish, $at));
            $this->tenants[$tenantId] = true;
        }

        $user = User::register(
            UserId::fromString(Ids::next()),
            $tenant,
            Email::fromString(\sprintf('%s-%s@example.test', $role->value, substr($tenantId, -12))),
            new PasswordHash('not-a-real-hash'),
            [$role],
            new DoctrineUserRepository($entityManager),
            $at,
        );
        $entityManager->persist($user);
        $entityManager->flush();

        $token = $container->get(JWTTokenManagerInterface::class)->create(SecurityUser::fromDomain($user));

        return $this->tokens[$key] = $token;
    }

    /**
     * The response of the last request, decoded.
     *
     * @return array<array-key, mixed>
     */
    protected function decodedResponse(): array
    {
        $content = (string) $this->client->getResponse()->getContent();
        $decoded = '' === $content ? [] : json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($decoded));

        return $decoded;
    }

    /**
     * The identifier of a resource that was just returned.
     *
     * @param array<array-key, mixed> $resource
     */
    protected function id(array $resource): string
    {
        $id = $resource['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    /**
     * A text field of a problem response, such as `detail` or `title`.
     *
     * @param array<array-key, mixed> $problem
     */
    protected function text(array $problem, string $field): string
    {
        $value = $problem[$field] ?? null;
        self::assertIsString($value, \sprintf('The problem has no text field "%s".', $field));

        return $value;
    }

    /**
     * One of the parameters of a problem response.
     *
     * @param array<array-key, mixed> $problem
     */
    protected function parameter(array $problem, string $name): mixed
    {
        $parameters = $problem['parameters'] ?? null;
        self::assertIsArray($parameters);

        return $parameters[$name] ?? null;
    }

    /**
     * The `paths` of the OpenAPI document the API publishes.
     *
     * @return array<array-key, mixed>
     */
    protected function openApiPaths(): array
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        $this->assertStatus(200);

        $document = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertIsArray($document['paths'] ?? null);
        self::assertIsArray($document['info'] ?? null);
        self::assertSame('Estiba WMS API', $document['info']['title'] ?? null);

        return $document['paths'];
    }

    protected function assertStatus(int $expected): void
    {
        self::assertSame($expected, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
    }

    /**
     * @param array<array-key, mixed> $problem
     */
    protected function assertProblem(int $status, string $code, array $problem): void
    {
        $this->assertStatus($status);
        self::assertStringStartsWith('application/problem+json', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame($code, $problem['code']);
        self::assertSame($status, $problem['status']);
    }
}
