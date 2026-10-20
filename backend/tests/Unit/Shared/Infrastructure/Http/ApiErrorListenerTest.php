<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Catalog\Domain\Exception\InvalidShelfLife;
use App\Shared\Application\TenantNotResolved;
use App\Shared\Infrastructure\Http\ApiErrorListener;
use App\Shared\Infrastructure\Http\ProblemFactory;
use App\Shared\Infrastructure\Http\RequestLanguage;
use App\Tests\Unit\Support\ErrorCatalog;
use App\Warehouse\Domain\Exception\InvalidWarehouseCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

final class ApiErrorListenerTest extends TestCase
{
    public function testAnswersABrokenDomainRuleInSpanishByDefault(): void
    {
        $response = $this->handle(InvalidWarehouseCode::forValue('not valid!'));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('warehouse.warehouse_code_invalid', $this->text($response, 'code'));
        self::assertSame('Entidad no procesable', $this->text($response, 'title'));
        self::assertStringStartsWith('"not valid!" no es un código de almacén válido.', $this->text($response, 'detail'));
        self::assertSame(['value' => 'not valid!'], $this->parameters($response));
        self::assertSame('es', $response->headers->get('Content-Language'));
        self::assertSame('Accept-Language', $response->headers->get('Vary'));
        self::assertStringStartsWith('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    public function testAnswersInEnglishWhenTheRequestAsksForIt(): void
    {
        $response = $this->handle(InvalidWarehouseCode::forValue('not valid!'), 'en');

        self::assertSame('Unprocessable entity', $this->text($response, 'title'));
        self::assertStringStartsWith('"not valid!" is not a valid warehouse code.', $this->text($response, 'detail'));
        self::assertSame('en', $response->headers->get('Content-Language'));
    }

    public function testTheCodeAndTheParametersDoNotDependOnTheLanguage(): void
    {
        $spanish = $this->handle(InvalidWarehouseCode::forValue('x'), 'es');
        $english = $this->handle(InvalidWarehouseCode::forValue('x'), 'en');

        self::assertSame($this->text($spanish, 'type'), $this->text($english, 'type'));
        self::assertSame($this->text($spanish, 'code'), $this->text($english, 'code'));
        self::assertSame($spanish->getStatusCode(), $english->getStatusCode());
        self::assertSame($this->parameters($spanish), $this->parameters($english));
        self::assertNotSame($this->text($spanish, 'detail'), $this->text($english, 'detail'));
    }

    public function testAParameterThatIsABooleanChoosesTheWording(): void
    {
        $tracked = $this->handle(InvalidShelfLife::forField('minShelfLifeAtReceiptDays', -3, true), 'en');
        $untracked = $this->handle(InvalidShelfLife::forField('minShelfLifeAtReceiptDays', 5, false), 'en');

        self::assertSame('The minimum shelf life at receipt must be zero or more days, got -3.', $this->text($tracked, 'detail'));
        self::assertSame('The minimum shelf life at receipt can only be set on products tracked by expiry date.', $this->text($untracked, 'detail'));
        // The response keeps the real boolean.
        self::assertTrue($this->parameters($tracked)['trackedByExpiry'] ?? null);
    }

    public function testFallsBackToTheEnglishMessageWhenACodeHasNoTranslationYet(): void
    {
        $response = $this->handle(UntranslatedError::make());

        self::assertSame('test.not_translated_yet', $this->text($response, 'code'));
        self::assertSame('English text for developers.', $this->text($response, 'detail'));
    }

    public function testAnswersAMissingTenantWithA401(): void
    {
        $response = $this->handle(TenantNotResolved::missing());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('shared.tenant_missing', $this->text($response, 'code'));
        self::assertSame('No autorizado', $this->text($response, 'title'));
    }

    #[DataProvider('httpErrors')]
    public function testGivesEveryClientErrorTheSameShape(\Throwable $error, int $status, string $code): void
    {
        $response = $this->handle($error);

        self::assertSame($status, $response->getStatusCode());
        self::assertSame($code, $this->text($response, 'code'));
        self::assertSame([], $this->parameters($response));
        self::assertArrayNotHasKey('trace', $this->body($response), 'An error response must never carry a stack trace.');
    }

    /**
     * @return iterable<string, array{\Throwable, int, string}>
     */
    public static function httpErrors(): iterable
    {
        yield 'a route that does not exist' => [new NotFoundHttpException('No route found'), 404, 'shared.not_found'];
        yield 'a method the resource does not offer' => [new MethodNotAllowedHttpException(['GET', 'POST']), 405, 'shared.method_not_allowed'];
        yield 'a content type the API does not read' => [new UnsupportedMediaTypeHttpException('text/plain'), 415, 'shared.unsupported_media_type'];
        yield 'a body that is not JSON' => [new NotEncodableValueException('Syntax error'), 400, 'shared.request_malformed'];
    }

    public function testLeavesRequestsOutsideTheApiAlone(): void
    {
        $event = $this->event(new NotFoundHttpException('No route'), '/somewhere-else');

        $this->listener(debug: false)($event);

        self::assertNull($event->getResponse());
    }

    public function testLeavesAnUnexpectedErrorAloneWhileDebugging(): void
    {
        $event = $this->event(new \RuntimeException('boom'));

        $this->listener(debug: true)($event);

        self::assertNull($event->getResponse(), 'In dev and test the stack trace must stay visible.');
    }

    public function testHidesAnUnexpectedErrorOutsideDebugAndLogsIt(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log(mixed $level, string|\Stringable $message, array $context = []): void
            {
                $this->messages[] = (\is_string($level) ? $level : 'unknown').': '.$message;
            }
        };
        $event = $this->event(new \RuntimeException('database password is hunter2'));

        $this->listener(debug: false, logger: $logger)($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame('shared.internal_error', $this->text($response, 'code'));
        self::assertStringNotContainsString('hunter2', (string) $response->getContent());
        self::assertSame(['error: Unexpected error in the API.'], $logger->messages);
    }

    private function handle(\Throwable $error, ?string $language = null): Response
    {
        $event = $this->event($error, '/api/warehouses', $language);

        $this->listener()($event);

        $response = $event->getResponse();
        self::assertNotNull($response, 'The listener should have answered.');

        return $response;
    }

    private function listener(bool $debug = true, ?AbstractLogger $logger = null): ApiErrorListener
    {
        $problems = new ProblemFactory(ErrorCatalog::translator(), new RequestLanguage());

        return new ApiErrorListener($problems, $logger ?? new class extends AbstractLogger {
            public function log(mixed $level, string|\Stringable $message, array $context = []): void
            {
            }
        }, $debug);
    }

    private function event(\Throwable $error, string $path = '/api/warehouses', ?string $language = null): ExceptionEvent
    {
        $request = Request::create($path);
        // Request::create adds `Accept-Language: en-us,en;q=0.5` on its own, so "no header" must be removed.
        if (null === $language) {
            $request->headers->remove('Accept-Language');
        } else {
            $request->headers->set('Accept-Language', $language);
        }

        return new ExceptionEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $error);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function body(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($decoded));

        return $decoded;
    }

    private function text(Response $response, string $field): string
    {
        $value = $this->body($response)[$field] ?? null;
        self::assertIsString($value, \sprintf('The problem has no text field "%s".', $field));

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function parameters(Response $response): array
    {
        $parameters = $this->body($response)['parameters'] ?? null;
        self::assertIsArray($parameters);

        return $parameters;
    }
}
