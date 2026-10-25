<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\OpenApi;

use Symfony\Component\Yaml\Yaml;

/**
 * Puts into the OpenAPI document what API Platform cannot know: a summary and the rules of each
 * operation, its role, an example of the body, the filters of the lists, the errors the API really
 * answers (the problem shape of ADR 0007, and not the generic ones of API Platform), the Bearer
 * security scheme and the `Accept-Language` header.
 *
 * What it says comes from `config/api/operations.yaml`. It works on the document as an array, so it
 * can be tested without the framework.
 */
final readonly class OpenApiDocumentation
{
    private const string AUTH_LOGIN = 'POST /api/auth/login';

    private const string LABEL = '/api/shipments/{id}/parcels/{sscc}/label.{format}';

    /**
     * @param array<string, string>               $tags       the description of each tag
     * @param array<string, array<string, mixed>> $operations by `METHOD /path`
     */
    public function __construct(
        private array $tags,
        private array $operations,
    ) {
    }

    public static function fromFile(string $path): self
    {
        $config = self::map(Yaml::parseFile($path));

        $tags = [];
        foreach (self::map($config['tags'] ?? null) as $name => $description) {
            $tags[$name] = \is_string($description) ? $description : '';
        }

        $operations = [];
        foreach (self::map($config['operations'] ?? null) as $key => $documentation) {
            $operations[$key] = self::map($documentation);
        }

        return new self($tags, $operations);
    }

    /**
     * @return list<string> the operations written down here
     */
    public function documented(): array
    {
        return array_keys($this->operations);
    }

    /**
     * The tag of an operation: the part of the API it belongs to.
     */
    public static function tagOf(string $path): string
    {
        if (1 === preg_match('#^/api/products/\{[^}]+\}/stock$#', $path)) {
            return 'Inventory';
        }

        $segment = explode('/', $path)[2] ?? '';

        return match ($segment) {
            'auth', 'me' => 'Identity',
            'warehouses', 'zones', 'locations' => 'Warehouse',
            'products' => 'Catalog',
            'stock-items', 'stock-movements' => 'Inventory',
            'asns', 'receipts', 'putaway-tasks' => 'Inbound',
            'orders', 'waves', 'pick-lists', 'shipments' => 'Outbound',
            'audit-log' => 'Audit',
            default => 'Other',
        };
    }

    /**
     * @param array<array-key, mixed> $document the OpenAPI document as API Platform made it
     *
     * @return array<string, mixed>
     */
    public function apply(array $document): array
    {
        $document = self::map($document);
        $document['info'] = $this->info(self::map($document['info'] ?? null));
        $document['tags'] = $this->tagList();
        $document['security'] = [['bearerAuth' => []]];
        $document['components'] = $this->components(self::map($document['components'] ?? null));
        $document['paths'] = $this->paths(self::map($document['paths'] ?? null));

        return $document;
    }

    /**
     * The value as a map with text keys, or an empty one when it is not an array.
     *
     * @return array<string, mixed>
     */
    private static function map(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
    }

    /**
     * The maps of a list, leaving out whatever is not one.
     *
     * @return list<array<string, mixed>>
     */
    private static function maps(mixed $value): array
    {
        $maps = [];
        foreach (\is_array($value) ? $value : [] as $item) {
            if (\is_array($item)) {
                $maps[] = self::map($item);
            }
        }

        return $maps;
    }

    /**
     * @param array<string, mixed> $info
     *
     * @return array<string, mixed>
     */
    private function info(array $info): array
    {
        $info['description'] = implode("\n\n", [
            'Warehouse management system for a fashion company that receives from suppliers and ships to stores.',
            '**Sign in** at `POST /api/auth/login` and send the token in every request as `Authorization: Bearer <token>`. A token lasts one hour. The tenant always comes from the signed-in user, and never from the request.',
            '**Roles** are `admin`, `supervisor` and `operator`, each including the ones below it. Reading needs any signed-in user. Changing needs a supervisor, except the work of the dock and the floor, which operators do. Every operation says its role.',
            '**Errors** are `application/problem+json` with a stable `code` to switch on. The `detail` is translated to the language of `Accept-Language`: Spanish by default, English on request. A request that is refused changes nothing.',
            '**Lists** that can grow are paged with `page` and `itemsPerPage` (30 by default, 100 at most), and come as a JSON array.',
            '**Ids** are UUIDs. **Dates** are ISO 8601 with time zone.',
        ]);

        return $info;
    }

    /**
     * @return list<array{name: string, description: string}>
     */
    private function tagList(): array
    {
        $list = [];
        foreach ($this->tags as $name => $description) {
            $list[] = ['name' => $name, 'description' => $description];
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $components
     *
     * @return array<string, mixed>
     */
    private function components(array $components): array
    {
        $components['securitySchemes'] = ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT', 'description' => 'The token that `POST /api/auth/login` returns.']];
        $components['parameters'] = [
            'AcceptLanguage' => [
                'name' => 'Accept-Language',
                'in' => 'header',
                'required' => false,
                'description' => 'The language of the texts in the answer: `es` (default) or `en`.',
                'schema' => ['type' => 'string', 'enum' => ['es', 'en'], 'default' => 'es'],
            ],
        ];
        $components['schemas'] = [...self::map($components['schemas'] ?? null), 'Problem' => $this->problemSchema()];
        $components['responses'] = [
            'BadRequest' => $this->problem('The request cannot be read: malformed JSON, a value of the wrong type, an id that is not a UUID or a filter with a value it does not know.', 400, 'Bad request', 'shared.identifier_invalid', 'Identifier "123" is not a valid UUID.', ['value' => '123']),
            'Unauthorized' => $this->problem('Not signed in: no token, a bad or an expired one, or wrong credentials.', 401, 'Unauthorized', 'shared.token_expired', 'The token has expired. Sign in again.', []),
            'Forbidden' => $this->problem('Signed in, but the role does not allow it.', 403, 'Forbidden', 'shared.forbidden', 'Your role does not allow this operation.', []),
            'NotFound' => $this->problem('The resource does not exist, or it belongs to another tenant.', 404, 'Not found', 'outbound.wave_not_found', 'There is no wave 0192f2b4-7c3e-7a15-9d5b-3c1f0a8e6b2d.', ['id' => '0192f2b4-7c3e-7a15-9d5b-3c1f0a8e6b2d']),
            'Conflict' => $this->problem('The change clashes with the current state: a code already used, something already done.', 409, 'Conflict', 'warehouse.location_already_blocked', 'The location A-03-02 is already blocked.', ['code' => 'A-03-02']),
            'Unprocessable' => $this->problem('The request is readable but breaks a rule of the domain. The `code` says which.', 422, 'Unprocessable entity', 'warehouse.warehouse_code_invalid', 'The warehouse code "not valid!" is not valid.', ['value' => 'not valid!']),
        ];

        return $components;
    }

    /**
     * @return array<string, mixed>
     */
    private function problemSchema(): array
    {
        return [
            'type' => 'object',
            'description' => 'An error, as RFC 9457 describes it. Switch on `code`, never on `detail`.',
            'required' => ['type', 'title', 'status', 'code', 'detail', 'parameters'],
            'properties' => [
                'type' => ['type' => 'string', 'description' => '`urn:estiba:error:` followed by the code.', 'example' => 'urn:estiba:error:outbound.wave_not_found'],
                'title' => ['type' => 'string', 'description' => 'The name of the HTTP status, translated.', 'example' => 'Not found'],
                'status' => ['type' => 'integer', 'description' => 'The HTTP status.', 'example' => 404],
                'code' => ['type' => 'string', 'description' => 'The stable identifier of the problem, such as `inventory.stock_negative`.', 'example' => 'outbound.wave_not_found'],
                'detail' => ['type' => 'string', 'description' => 'A sentence a person can read, translated.', 'example' => 'There is no wave 0192f2b4-7c3e-7a15-9d5b-3c1f0a8e6b2d.'],
                'parameters' => ['type' => 'object', 'description' => 'The values the sentence mentions, with their real types.', 'additionalProperties' => true],
            ],
        ];
    }

    /**
     * @param array<string, bool|float|int|string> $parameters
     *
     * @return array<string, mixed>
     */
    private function problem(string $description, int $status, string $title, string $code, string $detail, array $parameters): array
    {
        return [
            'description' => $description,
            'content' => [
                'application/problem+json' => [
                    'schema' => ['$ref' => '#/components/schemas/Problem'],
                    'example' => ['type' => 'urn:estiba:error:'.$code, 'title' => $title, 'status' => $status, 'code' => $code, 'detail' => $detail, 'parameters' => [] === $parameters ? new \stdClass() : $parameters],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $paths
     *
     * @return array<string, mixed>
     */
    private function paths(array $paths): array
    {
        foreach ($this->operations as $key => $documentation) {
            [$method, $path] = explode(' ', $key, 2);
            $method = strtolower($method);

            $item = self::map($paths[$path] ?? null);
            $operation = self::map($item[$method] ?? null);
            if ([] === $operation) {
                $operation = $this->outsideApiPlatform($path);
            }
            if ([] !== $operation) {
                $item[$method] = $this->operation($key, $path, $method, $operation, $documentation);
                $paths[$path] = $item;
            }
        }

        return $paths;
    }

    /**
     * The operations that a controller answers and API Platform knows nothing about.
     *
     * @return array<string, mixed>
     */
    private function outsideApiPlatform(string $path): array
    {
        if (self::LABEL !== $path) {
            return [];
        }

        return [
            'operationId' => 'api_shipments_parcels_label_get',
            'parameters' => [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'description' => 'Shipment identifier', 'schema' => ['type' => 'string', 'format' => 'uuid'], 'example' => '0192f2b4-7c3e-7a17-8d5b-3c1f0a8e6b27'],
                ['name' => 'sscc', 'in' => 'path', 'required' => true, 'description' => 'The SSCC of the parcel: 18 digits with the GS1 check digit.', 'schema' => ['type' => 'string', 'pattern' => '^[0-9]{18}$'], 'example' => '084000000000000017'],
                ['name' => 'format', 'in' => 'path', 'required' => true, 'description' => '`pdf` for a page to print anywhere, `zpl` for a Zebra printer.', 'schema' => ['type' => 'string', 'enum' => ['pdf', 'zpl']], 'example' => 'pdf'],
            ],
            'responses' => [
                '200' => [
                    'description' => 'The label',
                    'headers' => ['Content-Language' => ['description' => 'The language of the texts on the label.', 'schema' => ['type' => 'string']]],
                    'content' => [
                        'application/pdf' => ['schema' => ['type' => 'string', 'format' => 'binary']],
                        'text/plain' => ['schema' => ['type' => 'string'], 'example' => "^XA\n^CI28\n^PW812\n^LL1218\n^FO100,700^BCN,260,N,N,N,N^FD>;>800084000000000000017^FS\n^XZ\n"],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $operation
     * @param array<string, mixed> $documentation
     *
     * @return array<string, mixed>
     */
    private function operation(string $key, string $path, string $method, array $operation, array $documentation): array
    {
        $isLogin = self::AUTH_LOGIN === $key;
        $role = $isLogin ? null : (\is_string($documentation['role'] ?? null) ? $documentation['role'] : 'operator');

        $operation['tags'] = [self::tagOf($path)];
        $operation['summary'] = \is_string($documentation['summary'] ?? null) ? $documentation['summary'] : ($operation['summary'] ?? '');
        $operation['description'] = $this->description($documentation, $role);
        $operation['parameters'] = $this->parameters($operation['parameters'] ?? null, $documentation, $method, $path);
        $operation['responses'] = $this->responses($operation['responses'] ?? null, $path, $method, $role, $isLogin, $documentation);
        if ($isLogin) {
            $operation['security'] = [];
        }
        if (\array_key_exists('example', $documentation)) {
            $operation['requestBody'] = $this->requestBody(self::map($operation['requestBody'] ?? null), $documentation['example']);
        }

        return $operation;
    }

    /**
     * The body with its example in every format it accepts, or a new optional one for an operation
     * that reads a body API Platform does not know about.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function requestBody(array $body, mixed $example): array
    {
        $content = self::map($body['content'] ?? null);
        if ([] === $content) {
            return ['required' => false, 'content' => ['application/json' => ['schema' => ['type' => 'object'], 'example' => $example]]];
        }

        foreach ($content as $contentType => $media) {
            $content[$contentType] = [...self::map($media), 'example' => $example];
        }
        $body['content'] = $content;

        return $body;
    }

    /**
     * @param array<string, mixed> $documentation
     */
    private function description(array $documentation, ?string $role): string
    {
        $parts = [];
        if (\is_string($documentation['description'] ?? null)) {
            $parts[] = $documentation['description'];
        }
        $parts[] = match ($role) {
            null => '**Role:** none, it is how you sign in.',
            'operator' => '**Role:** any signed-in user.',
            'supervisor' => '**Role:** supervisor or admin.',
            default => '**Role:** '.$role.'.',
        };

        return implode("\n\n", $parts);
    }

    /**
     * @param array<string, mixed> $documentation
     *
     * @return list<array<string, mixed>>
     */
    private function parameters(mixed $existing, array $documentation, string $method, string $path): array
    {
        $parameters = self::maps($existing);

        if ('get' === $method && $this->isCollection($parameters)) {
            $parameters = false === ($documentation['paged'] ?? true) ? $this->withoutPaging($parameters) : $this->withPaging($parameters);
        }
        foreach (self::maps($documentation['query'] ?? null) as $filter) {
            if (\is_string($filter['name'] ?? null)) {
                $parameters[] = $this->filter($filter);
            }
        }

        if ('/api/auth/login' !== $path) {
            $parameters[] = ['$ref' => '#/components/parameters/AcceptLanguage'];
        }

        return $parameters;
    }

    /**
     * A filter of a list, as a query parameter.
     *
     * @param array<string, mixed> $filter
     *
     * @return array<string, mixed>
     */
    private function filter(array $filter): array
    {
        $enum = \is_array($filter['enum'] ?? null) ? array_values($filter['enum']) : null;
        $keep = static fn (mixed $value): bool => null !== $value;

        return array_filter([
            'name' => $filter['name'],
            'in' => 'query',
            'required' => false,
            'description' => $filter['description'] ?? null,
            'schema' => array_filter(['type' => 'string', 'enum' => $enum], $keep),
            'example' => $filter['example'] ?? ($enum[0] ?? null),
        ], $keep);
    }

    /**
     * @param list<array<string, mixed>> $parameters
     */
    private function isCollection(array $parameters): bool
    {
        return \in_array('page', array_column($parameters, 'name'), true);
    }

    /**
     * @param list<array<string, mixed>> $parameters
     *
     * @return list<array<string, mixed>>
     */
    private function withoutPaging(array $parameters): array
    {
        return array_values(array_filter($parameters, static fn (array $parameter): bool => !\in_array($parameter['name'] ?? null, ['page', 'itemsPerPage'], true)));
    }

    /**
     * @param list<array<string, mixed>> $parameters
     *
     * @return list<array<string, mixed>>
     */
    private function withPaging(array $parameters): array
    {
        if (!\in_array('itemsPerPage', array_column($parameters, 'name'), true)) {
            $parameters[] = ['name' => 'itemsPerPage', 'in' => 'query', 'required' => false, 'description' => 'How many to show in a page: 30 by default, 100 at most.', 'schema' => ['type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 100]];
        }

        return $parameters;
    }

    /**
     * Keeps the answer that works and replaces the errors API Platform guesses with the ones the API
     * gives, by reference to the problem responses.
     *
     * @param array<string, mixed> $documentation
     *
     * @return array<int|string, mixed>
     */
    private function responses(mixed $existing, string $path, string $method, ?string $role, bool $isLogin, array $documentation): array
    {
        $responses = [];
        foreach (self::map($existing) as $status => $response) {
            if ((int) $status < 400) {
                $responses[$status] = $response;
            }
        }

        $changes = \in_array($method, ['post', 'patch'], true) && !$isLogin;
        $byId = str_contains($path, '{');

        if (\array_key_exists('example', $documentation) || $byId || $isLogin || \array_key_exists('query', $documentation)) {
            $responses['400'] = ['$ref' => '#/components/responses/BadRequest'];
        }
        $responses['401'] = ['$ref' => '#/components/responses/Unauthorized'];
        if (null !== $role && 'operator' !== $role) {
            $responses['403'] = ['$ref' => '#/components/responses/Forbidden'];
        }
        if ($byId) {
            $responses['404'] = ['$ref' => '#/components/responses/NotFound'];
        }
        if ($changes && false !== ($documentation['conflicts'] ?? true)) {
            $responses['409'] = ['$ref' => '#/components/responses/Conflict'];
        }
        if ($changes) {
            $responses['422'] = ['$ref' => '#/components/responses/Unprocessable'];
        }
        ksort($responses);

        return $responses;
    }
}
