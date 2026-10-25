<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Identity\Domain\Role;
use App\Tests\Functional\Support\ApiTestCase;
use App\Tests\Unit\Support\Ids;
use Symfony\Component\Yaml\Yaml;

/**
 * The OpenAPI document the API publishes is complete and tells the truth (E04-05, ADR 0019): every
 * operation is explained, every body has an example that fits it, and the role each operation says is
 * the one the firewall asks for.
 */
final class OpenApiDocumentTest extends ApiTestCase
{
    private const array METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /** @var array<string, mixed>|null */
    private ?array $document = null;

    public function testEveryOperationOfTheApiIsDocumented(): void
    {
        $published = array_keys($this->operations());
        $written = $this->written();

        self::assertSame([], array_values(array_diff($published, $written)), 'Operations with nothing written in config/api/operations.yaml.');
        self::assertSame([], array_values(array_diff($written, $published)), 'Entries of config/api/operations.yaml for an operation the API does not have.');
    }

    public function testEveryOperationHasItsOwnSummaryATagAndTheLanguageHeader(): void
    {
        foreach ($this->operations() as $key => $operation) {
            $summary = $this->asText($operation['summary'] ?? null);
            self::assertDoesNotMatchRegularExpression('/ resource(s)?\.?$/', $summary, $key.' keeps the summary API Platform wrote.');
            self::assertStringContainsString('**Role:**', $this->asText($operation['description'] ?? null), $key);
            self::assertCount(1, $this->asList($operation['tags'] ?? null), $key);
            if ('POST /api/auth/login' !== $key) {
                self::assertContains(['$ref' => '#/components/parameters/AcceptLanguage'], $this->asList($operation['parameters'] ?? null), $key);
            }
        }
    }

    public function testEveryTagIsExplained(): void
    {
        $described = [];
        foreach ($this->asList($this->document()['tags'] ?? null) as $tag) {
            $tag = $this->asMap($tag);
            $described[$this->asText($tag['name'] ?? null)] = $this->asText($tag['description'] ?? null);
        }

        foreach ($this->operations() as $key => $operation) {
            foreach ($this->asList($operation['tags'] ?? null) as $tag) {
                self::assertArrayHasKey($this->asText($tag), $described, $key);
                self::assertNotSame('', $described[$this->asText($tag)]);
            }
        }
        self::assertArrayNotHasKey('Other', $described);
    }

    public function testEveryRequestBodyHasAnExampleThatFitsItsSchema(): void
    {
        foreach ($this->operations() as $key => $operation) {
            $content = $this->asMap($this->asMap($operation['requestBody'] ?? [])['content'] ?? []);

            foreach ($content as $contentType => $media) {
                $media = $this->asMap($media);
                self::assertArrayHasKey('example', $media, $key.' '.$contentType.' has no example.');
                $example = $this->asMap($media['example']);

                $schema = $this->resolve($this->asMap($media['schema'] ?? []));
                $properties = array_keys($this->asMap($schema['properties'] ?? []));
                if ([] === $properties) {
                    continue;
                }
                self::assertSame([], array_values(array_diff(array_keys($example), $properties)), $key.': the example has a field the body does not.');
                if (!str_contains($contentType, 'merge-patch')) {
                    $required = array_map($this->asText(...), $this->asList($schema['required'] ?? []));
                    self::assertSame([], array_values(array_diff($required, array_keys($example))), $key.': the example lacks a required field.');
                }
            }
        }
    }

    public function testTheErrorsAreTheOnesTheApiReallyAnswers(): void
    {
        $responses = $this->asMap($this->components()['responses'] ?? null);

        foreach (['BadRequest' => 400, 'Unauthorized' => 401, 'Forbidden' => 403, 'NotFound' => 404, 'Conflict' => 409, 'Unprocessable' => 422] as $name => $status) {
            $example = $this->asMap($this->asMap($this->asMap($this->asMap($responses[$name] ?? null)['content'] ?? null)['application/problem+json'] ?? null)['example'] ?? null);
            self::assertSame($status, $example['status'] ?? null, $name);
            self::assertSame('urn:estiba:error:'.$this->asText($example['code'] ?? null), $example['type'] ?? null, $name);
        }

        foreach ($this->operations() as $key => $operation) {
            $answers = $this->asMap($operation['responses'] ?? null);
            foreach ($answers as $status => $response) {
                if ((int) $status >= 400) {
                    self::assertArrayHasKey('$ref', $this->asMap($response), $key.' '.$status.' keeps an error that API Platform guessed.');
                }
            }
            self::assertContains('401', array_map(strval(...), array_keys($answers)), $key);
        }
    }

    public function testTheProblemSchemaIsTheShapeOfEveryError(): void
    {
        $problem = $this->asMap($this->asMap($this->components()['schemas'] ?? null)['Problem'] ?? null);

        self::assertSame(['type', 'title', 'status', 'code', 'detail', 'parameters'], $problem['required'] ?? null);
        self::assertSame($problem['required'], array_keys($this->asMap($problem['properties'] ?? null)));
    }

    public function testEveryoneSignsInWithABearerTokenExceptWhoIsSigningIn(): void
    {
        $schemes = $this->asMap($this->components()['securitySchemes'] ?? null);

        self::assertSame([['bearerAuth' => []]], $this->document()['security'] ?? null);
        self::assertSame('bearer', $this->asMap($schemes['bearerAuth'] ?? null)['scheme'] ?? null);
        self::assertSame([], $this->operations()['POST /api/auth/login']['security'] ?? null);
    }

    public function testTheListsSayTheirFilters(): void
    {
        $operations = $this->operations();
        $names = fn (string $key): array => array_column($this->parametersOf($operations[$key] ?? []), 'name');

        self::assertContains('status', $names('GET /api/waves'));
        self::assertContains('itemsPerPage', $names('GET /api/waves'));
        self::assertContains('eventType', $names('GET /api/audit-log'));
        self::assertContains('from', $names('GET /api/audit-log'));
        self::assertNotContains('page', $names('GET /api/warehouses'), 'Warehouses are not paged.');
        self::assertNotContains('page', $names('GET /api/pick-lists'), 'Pick lists are not paged.');
    }

    public function testTheLabelOfAParcelIsDocumentedEvenThoughItIsNotAResource(): void
    {
        $label = $this->operations()['GET /api/shipments/{id}/parcels/{sscc}/label.{format}'] ?? [];

        $format = $this->parametersOf($label)[2] ?? [];
        self::assertSame(['pdf', 'zpl'], $this->asMap($format['schema'] ?? null)['enum'] ?? null);
        $success = array_values($this->asMap($label['responses'] ?? null))[0] ?? null;
        self::assertArrayHasKey('application/pdf', $this->asMap($this->asMap($success)['content'] ?? null));
        self::assertSame(['Outbound'], $label['tags'] ?? null);
    }

    /**
     * The role an operation says is the one the firewall asks for: an operator is refused exactly the
     * operations that say supervisor, and a supervisor is refused none. The firewall answers before
     * anything else, so the body and the ids do not matter.
     */
    public function testTheRoleOfEachOperationIsTheOneTheFirewallAsksFor(): void
    {
        $mismatches = [];
        foreach ($this->operations() as $key => $operation) {
            if ('POST /api/auth/login' === $key) {
                continue;
            }
            [$method, $path] = explode(' ', $key, 2);
            $uri = (string) preg_replace(['#\{format\}#', '#\{sscc\}#', '#\{position\}#', '#\{[a-zA-Z]+\}#'], ['pdf', '084000000000000017', '1', Ids::next()], $path);
            $says = str_contains($this->asText($operation['description'] ?? null), '**Role:** any signed-in user.') ? 'operator' : 'supervisor';

            foreach ([Role::Operator, Role::Supervisor] as $role) {
                $this->role = $role;
                $this->client->request($method, $uri, [], [], $this->headers(), 'GET' === $method ? null : '{}');
                $refused = 403 === $this->client->getResponse()->getStatusCode();
                $shouldRefuse = Role::Operator === $role && 'supervisor' === $says;
                if ($refused !== $shouldRefuse) {
                    $mismatches[] = \sprintf('%s says %s but a%s %s is %s', $key, $says, Role::Operator === $role ? 'n' : '', $role->value, $refused ? 'refused' : 'allowed');
                }
            }
        }

        self::assertSame([], $mismatches);
    }

    /**
     * @return array<string, mixed>
     */
    private function document(): array
    {
        if (null === $this->document) {
            $this->client->request('GET', '/api/docs.jsonopenapi');
            $this->assertStatus(200);
            $this->document = $this->asMap(json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR));
        }

        return $this->document;
    }

    /**
     * @return array<string, array<string, mixed>> by `METHOD /path`
     */
    private function operations(): array
    {
        $operations = [];
        foreach ($this->asMap($this->document()['paths'] ?? null) as $path => $item) {
            $item = $this->asMap($item);
            foreach (self::METHODS as $method) {
                if (isset($item[$method])) {
                    $operations[strtoupper($method).' '.$path] = $this->asMap($item[$method]);
                }
            }
        }

        return $operations;
    }

    /**
     * @return list<string>
     */
    private function written(): array
    {
        $file = $this->asMap(Yaml::parseFile(\dirname(__DIR__, 3).'/config/api/operations.yaml'));

        return array_keys($this->asMap($file['operations'] ?? null));
    }

    /**
     * @return array<string, mixed>
     */
    private function components(): array
    {
        return $this->asMap($this->document()['components'] ?? null);
    }

    /**
     * The parameters of an operation, leaving out the references.
     *
     * @param array<string, mixed> $operation
     *
     * @return list<array<string, mixed>>
     */
    private function parametersOf(array $operation): array
    {
        $parameters = [];
        foreach ($this->asList($operation['parameters'] ?? null) as $parameter) {
            $parameter = $this->asMap($parameter);
            if (isset($parameter['name'])) {
                $parameters[] = $parameter;
            }
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function resolve(array $schema): array
    {
        $reference = $schema['$ref'] ?? null;
        if (!\is_string($reference)) {
            return $schema;
        }

        $resolved = $this->asMap($this->asMap($this->components()['schemas'] ?? null)[substr($reference, \strlen('#/components/schemas/'))] ?? null);
        self::assertNotSame([], $resolved, $reference);

        return $resolved;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor($this->tenant, $this->role),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function asMap(mixed $value): array
    {
        self::assertIsArray($value);

        $map = [];
        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
    }

    /**
     * @return list<mixed>
     */
    private function asList(mixed $value): array
    {
        self::assertIsArray($value);

        return array_values($value);
    }

    private function asText(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
