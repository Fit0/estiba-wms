<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\OpenApi;

use App\Shared\Infrastructure\OpenApi\OpenApiDocumentation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the documentation adds to the document that API Platform writes.
 */
final class OpenApiDocumentationTest extends TestCase
{
    public function testTheSummaryTheRoleAndTheTagReplaceTheGenericOnes(): void
    {
        $operation = ['paths', '/api/waves/{id}/release', 'post'];

        self::assertSame('Send a wave to the floor', $this->find(...$operation, ...['summary']));
        self::assertSame(['Outbound'], $this->find(...$operation, ...['tags']));
        self::assertSame("Makes the pick lists.\n\n**Role:** supervisor or admin.", $this->find(...$operation, ...['description']));
    }

    public function testAnOperationForAnyUserSaysSo(): void
    {
        self::assertSame('**Role:** any signed-in user.', $this->find('paths', '/api/waves', 'get', 'description'));
    }

    public function testTheBodyGetsItsExampleInEveryFormatItAccepts(): void
    {
        self::assertSame(['orderIds' => ['0192f2b4-7c3e-7a14-8d5b-3c1f0a8e6b2b']], $this->find('paths', '/api/waves', 'post', 'requestBody', 'content', 'application/json', 'example'));
    }

    public function testAnOperationWithAnOptionalBodyThatApiPlatformDoesNotKnowGetsOne(): void
    {
        self::assertFalse($this->find('paths', '/api/waves/{id}/release', 'post', 'requestBody', 'required'));
        self::assertSame(['pickLists' => 2], $this->find('paths', '/api/waves/{id}/release', 'post', 'requestBody', 'content', 'application/json', 'example'));
    }

    public function testTheErrorsThatApiPlatformGuessedAreReplacedByTheOnesTheApiGives(): void
    {
        $responses = $this->find('paths', '/api/waves', 'post', 'responses');

        self::assertSame(['201', '400', '401', '403', '409', '422'], $this->statuses($responses));
        self::assertSame(['$ref' => '#/components/responses/Unprocessable'], $this->find('paths', '/api/waves', 'post', 'responses', '422'));
        self::assertSame(['description' => 'Wave resource created'], $this->find('paths', '/api/waves', 'post', 'responses', '201'), 'What works is kept as it was.');
    }

    /**
     * @param list<string> $statuses
     */
    #[DataProvider('errorsOf')]
    public function testEachOperationGetsTheErrorsThatCanHappenToIt(string $path, string $method, array $statuses): void
    {
        self::assertSame($statuses, $this->statuses($this->find('paths', $path, $method, 'responses')));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function errorsOf(): iterable
    {
        yield 'a list for any user' => ['/api/waves', 'get', ['200', '400', '401']];
        yield 'one by its id' => ['/api/waves/{id}', 'get', ['200', '400', '401', '404']];
        yield 'a change by id for a supervisor' => ['/api/waves/{id}/cancel', 'post', ['200', '400', '401', '403', '404', '409', '422']];
        yield 'a change that never clashes' => ['/api/waves/{id}/release', 'post', ['200', '400', '401', '403', '404', '422']];
        yield 'signing in' => ['/api/auth/login', 'post', ['200', '400', '401']];
    }

    public function testSigningInNeedsNoTokenAndEverythingElseDoes(): void
    {
        self::assertSame([['bearerAuth' => []]], $this->find('security'));
        self::assertSame([], $this->find('paths', '/api/auth/login', 'post', 'security'));
        self::assertSame('bearer', $this->find('components', 'securitySchemes', 'bearerAuth', 'scheme'));
    }

    public function testEveryOperationAcceptsTheLanguageHeaderExceptSigningIn(): void
    {
        self::assertContains(['$ref' => '#/components/parameters/AcceptLanguage'], $this->listAt('paths', '/api/waves', 'get', 'parameters'));
        self::assertNotContains(['$ref' => '#/components/parameters/AcceptLanguage'], $this->listAt('paths', '/api/auth/login', 'post', 'parameters'));
        self::assertSame(['es', 'en'], $this->find('components', 'parameters', 'AcceptLanguage', 'schema', 'enum'));
    }

    public function testAListGetsItsPagingAndItsFilters(): void
    {
        $parameters = $this->listAt('paths', '/api/waves', 'get', 'parameters');

        self::assertSame(['page', 'itemsPerPage', 'status'], array_column($parameters, 'name'));
        self::assertSame(['planned', 'released'], $this->find('paths', '/api/waves', 'get', 'parameters', '2', 'schema', 'enum'));
        self::assertSame('planned', $this->find('paths', '/api/waves', 'get', 'parameters', '2', 'example'));
        self::assertFalse($this->find('paths', '/api/waves', 'get', 'parameters', '2', 'required'));
    }

    public function testAListThatIsNotPagedLosesThePagingApiPlatformAdded(): void
    {
        self::assertSame([['$ref' => '#/components/parameters/AcceptLanguage']], $this->find('paths', '/api/pick-lists', 'get', 'parameters'));
    }

    public function testTheTagsAreListedWithTheirDescription(): void
    {
        self::assertSame([['name' => 'Outbound', 'description' => 'Orders and waves.'], ['name' => 'Identity', 'description' => 'Signing in.']], $this->find('tags'));
    }

    public function testTheProblemSchemaAndResponsesAreAddedToTheComponents(): void
    {
        self::assertSame(['type', 'title', 'status', 'code', 'detail', 'parameters'], $this->find('components', 'schemas', 'Problem', 'required'));
        self::assertSame(['BadRequest', 'Unauthorized', 'Forbidden', 'NotFound', 'Conflict', 'Unprocessable'], array_keys($this->mapAt('components', 'responses')));
        self::assertSame(409, $this->find('components', 'responses', 'Conflict', 'content', 'application/problem+json', 'example', 'status'));
        self::assertSame(['type' => 'object'], $this->find('components', 'schemas', 'Wave'), 'What API Platform wrote is kept.');
    }

    public function testAnOperationNobodyWroteDownIsLeftAsApiPlatformMadeIt(): void
    {
        self::assertSame('Retrieves something.', $this->find('paths', '/api/other', 'get', 'summary'));
    }

    public function testAnOperationOutsideApiPlatformIsAddedFromTheTable(): void
    {
        $label = ['paths', '/api/shipments/{id}/parcels/{sscc}/label.{format}', 'get'];

        self::assertSame(['pdf', 'zpl'], $this->find(...$label, ...['parameters', '2', 'schema', 'enum']));
        self::assertSame(['Outbound'], $this->find(...$label, ...['tags']));
        self::assertArrayHasKey('application/pdf', $this->mapAt(...$label, ...['responses', '200', 'content']));
        self::assertContains('404', $this->statuses($this->find(...$label, ...['responses'])));
    }

    #[DataProvider('tags')]
    public function testEachPartOfTheApiHasItsTag(string $path, string $tag): void
    {
        self::assertSame($tag, OpenApiDocumentation::tagOf($path));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tags(): iterable
    {
        yield 'login' => ['/api/auth/login', 'Identity'];
        yield 'me' => ['/api/me', 'Identity'];
        yield 'zones' => ['/api/zones/{id}', 'Warehouse'];
        yield 'products' => ['/api/products/{id}/gtins', 'Catalog'];
        yield 'the stock of a product' => ['/api/products/{productId}/stock', 'Inventory'];
        yield 'stock items' => ['/api/stock-items', 'Inventory'];
        yield 'receipts' => ['/api/receipts/{id}/close', 'Inbound'];
        yield 'shipments' => ['/api/shipments/{id}/parcels', 'Outbound'];
        yield 'the audit log' => ['/api/audit-log', 'Audit'];
        yield 'something unknown' => ['/api/robots', 'Other'];
    }

    public function testTheOperationsWrittenDownAreListed(): void
    {
        self::assertContains('POST /api/waves', $this->documentation()->documented());
    }

    /**
     * The part of the documented document at the end of a path of keys.
     */
    private function find(string ...$path): mixed
    {
        $value = $this->documentation()->apply($this->generated());
        foreach ($path as $key) {
            self::assertIsArray($value, 'Nothing at '.implode(' > ', $path));
            self::assertArrayHasKey($key, $value, 'Nothing at '.implode(' > ', $path));
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function mapAt(string ...$path): array
    {
        $value = $this->find(...$path);
        self::assertIsArray($value);

        return $value;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function listAt(string ...$path): array
    {
        $list = [];
        foreach ($this->mapAt(...$path) as $item) {
            self::assertIsArray($item);
            $list[] = $item;
        }

        return $list;
    }

    /**
     * The statuses of the responses as text: PHP turns a numeric key into a number.
     *
     * @return list<string>
     */
    private function statuses(mixed $responses): array
    {
        self::assertIsArray($responses);

        return array_map(strval(...), array_keys($responses));
    }

    private function documentation(): OpenApiDocumentation
    {
        return new OpenApiDocumentation(
            ['Outbound' => 'Orders and waves.', 'Identity' => 'Signing in.'],
            [
                'POST /api/auth/login' => ['summary' => 'Sign in and get a token', 'example' => ['email' => 'ana@example.test', 'password' => 'secret']],
                'GET /api/waves' => ['summary' => 'List the waves', 'role' => 'operator', 'query' => [['name' => 'status', 'description' => 'Only this status.', 'enum' => ['planned', 'released']]]],
                'POST /api/waves' => ['summary' => 'Group orders into a wave', 'description' => 'Every order must be allocated.', 'role' => 'supervisor', 'example' => ['orderIds' => ['0192f2b4-7c3e-7a14-8d5b-3c1f0a8e6b2b']]],
                'GET /api/waves/{id}' => ['summary' => 'Get a wave', 'role' => 'operator'],
                'POST /api/waves/{id}/release' => ['summary' => 'Send a wave to the floor', 'description' => 'Makes the pick lists.', 'role' => 'supervisor', 'example' => ['pickLists' => 2], 'conflicts' => false],
                'POST /api/waves/{id}/cancel' => ['summary' => 'Cancel a wave', 'role' => 'supervisor'],
                'GET /api/pick-lists' => ['summary' => 'List the pick lists', 'role' => 'operator', 'paged' => false],
                'GET /api/shipments/{id}/parcels/{sscc}/label.{format}' => ['summary' => 'Get the label of a parcel', 'role' => 'operator'],
            ],
        );
    }

    /**
     * A small document in the shape API Platform makes.
     *
     * @return array<string, mixed>
     */
    private function generated(): array
    {
        $guessedErrors = [
            '400' => ['description' => 'Invalid input', 'content' => ['application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
            '422' => ['description' => 'An error occurred', 'content' => ['application/problem+json' => ['schema' => ['$ref' => '#/components/schemas/ConstraintViolation']]]],
        ];
        $id = [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]];
        $paging = [['name' => 'page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 1]]];

        return [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Estiba WMS API', 'version' => '0.1.0', 'description' => 'Generated.'],
            'paths' => [
                '/api/auth/login' => ['post' => ['summary' => 'Creates a user token.', 'responses' => ['200' => ['description' => 'User token created']], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object']]]]]],
                '/api/waves' => [
                    'get' => ['summary' => 'Retrieves the collection of Wave resources.', 'parameters' => $paging, 'responses' => ['200' => ['description' => 'Wave collection']]],
                    'post' => ['summary' => 'Creates a Wave resource.', 'responses' => ['201' => ['description' => 'Wave resource created']] + $guessedErrors, 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object']]]]],
                ],
                '/api/waves/{id}' => ['get' => ['summary' => 'Retrieves a Wave resource.', 'parameters' => $id, 'responses' => ['200' => ['description' => 'Wave resource']]]],
                '/api/waves/{id}/release' => ['post' => ['summary' => 'Creates a Wave resource.', 'parameters' => $id, 'responses' => ['200' => ['description' => 'Wave resource created']] + $guessedErrors]],
                '/api/waves/{id}/cancel' => ['post' => ['summary' => 'Creates a Wave resource.', 'parameters' => $id, 'responses' => ['200' => ['description' => 'Wave resource created']] + $guessedErrors]],
                '/api/pick-lists' => ['get' => ['summary' => 'Retrieves the collection of PickList resources.', 'parameters' => $paging, 'responses' => ['200' => ['description' => 'PickList collection']]]],
                '/api/other' => ['get' => ['summary' => 'Retrieves something.', 'responses' => ['200' => ['description' => 'Something']]]],
            ],
            'components' => ['schemas' => ['Wave' => ['type' => 'object']]],
        ];
    }
}
