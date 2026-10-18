<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use App\Shared\Infrastructure\Http\HeaderCurrentTenant;
use App\Tests\Unit\Support\Ids;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base of the API tests: a client, an empty database built from the mapping, and a tenant
 * to act as. Requests send and receive JSON.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected string $tenant;

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
        $tenant ??= $this->tenant;
        if ('' !== $tenant) {
            $headers['HTTP_'.strtoupper(str_replace('-', '_', HeaderCurrentTenant::HEADER))] = $tenant;
        }

        $this->client->request($method, $uri, $query, [], $headers, null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));

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
