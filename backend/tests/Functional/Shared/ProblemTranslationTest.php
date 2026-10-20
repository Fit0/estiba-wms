<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Tests\Functional\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The API answers every error in the language the request asks for, in one shape (ADR 0004).
 */
final class ProblemTranslationTest extends ApiTestCase
{
    public function testAnswersInSpanishWhenTheRequestSaysNothing(): void
    {
        $problem = $this->request('POST', '/api/warehouses', ['code' => 'not valid!', 'name' => 'Madrid']);

        $this->assertProblem(422, 'warehouse.warehouse_code_invalid', $problem);
        self::assertSame('es', $this->header('Content-Language'));
        self::assertSame('Entidad no procesable', $this->text($problem, 'title'));
        self::assertStringStartsWith('"not valid!" no es un código de almacén válido.', $this->text($problem, 'detail'));
    }

    public function testAnswersInEnglishWhenAskedTo(): void
    {
        $this->acceptLanguage = 'en';

        $problem = $this->request('POST', '/api/warehouses', ['code' => 'not valid!', 'name' => 'Madrid']);

        $this->assertProblem(422, 'warehouse.warehouse_code_invalid', $problem);
        self::assertSame('en', $this->header('Content-Language'));
        self::assertSame('Unprocessable entity', $this->text($problem, 'title'));
        self::assertStringStartsWith('"not valid!" is not a valid warehouse code.', $this->text($problem, 'detail'));
    }

    #[DataProvider('languageHeaders')]
    public function testPicksTheLanguageFromTheHeader(string $header, string $expected): void
    {
        $this->acceptLanguage = $header;

        $this->request('POST', '/api/warehouses', ['code' => 'x', 'name' => 'Madrid']);

        self::assertSame($expected, $this->header('Content-Language'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function languageHeaders(): iterable
    {
        yield 'regional English' => ['en-GB,en;q=0.9', 'en'];
        yield 'the highest quality wins' => ['es;q=0.4,en;q=0.9', 'en'];
        yield 'a language the API does not speak' => ['fr-FR,fr;q=0.9', 'es'];
    }

    public function testChoosesTheWordingFromTheParametersOfTheError(): void
    {
        $this->acceptLanguage = 'en';
        $warehouse = $this->request('POST', '/api/warehouses', ['code' => 'MAD1', 'name' => 'Madrid']);
        $zone = $this->request('POST', '/api/warehouses/'.$this->id($warehouse).'/zones', ['code' => 'FAST', 'name' => 'Fast', 'storageClass' => 'ambient', 'pickSequence' => 1]);

        $problem = $this->request('POST', '/api/warehouses/'.$this->id($warehouse).'/locations', [
            'zoneId' => $this->id($zone),
            'code' => 'A-03-02',
            'type' => 'reserve',
            'pickSequence' => 10,
            'maxWeightKg' => 0,
        ]);

        $this->assertProblem(422, 'warehouse.capacity_invalid', $problem);
        self::assertSame('The maximum weight must be a positive whole number, got 0.', $this->text($problem, 'detail'));
        self::assertSame(0, $this->parameter($problem, 'value'));
    }

    public function testAnswersAMissingTokenInTheRequestedLanguage(): void
    {
        $this->acceptLanguage = 'en';

        $problem = $this->request('GET', '/api/warehouses', null, '');

        $this->assertProblem(401, 'shared.unauthenticated', $problem);
        self::assertSame('You need to sign in to use this resource. Send the token in the Authorization: Bearer header.', $this->text($problem, 'detail'));
        self::assertSame('Bearer', $this->header('WWW-Authenticate'));
    }

    public function testGivesTheErrorsThatAreNotDomainRulesTheSameShape(): void
    {
        $notFound = $this->request('GET', '/api/nope');
        $this->assertProblem(404, 'shared.not_found', $notFound);
        self::assertSame('No existe el recurso solicitado.', $this->text($notFound, 'detail'));

        $notAllowed = $this->request('DELETE', '/api/warehouses');
        $this->assertProblem(405, 'shared.method_not_allowed', $notAllowed);

        $missingField = $this->request('POST', '/api/warehouses', ['code' => 'MAD1']);
        $this->assertProblem(400, 'shared.request_malformed', $missingField);

        foreach ([$notFound, $notAllowed, $missingField] as $problem) {
            self::assertArrayNotHasKey('trace', $problem);
            self::assertSame('urn:estiba:error:'.$this->text($problem, 'code'), $this->text($problem, 'type'));
        }
    }

    public function testAnswersABodyThatIsNotJson(): void
    {
        $this->client->request('POST', '/api/warehouses', [], [], $this->jsonHeaders(), '{bad');

        $problem = $this->decodedResponse();
        $this->assertProblem(400, 'shared.request_malformed', $problem);
        self::assertStringStartsWith('No se ha podido leer el cuerpo de la petición.', $this->text($problem, 'detail'));
    }

    public function testAnswersAFormatAndAContentTypeTheApiDoesNotOffer(): void
    {
        $this->client->request('GET', '/api/warehouses', [], [], ['HTTP_ACCEPT' => 'application/xml', 'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor($this->tenant, $this->role)]);
        $this->assertProblem(406, 'shared.not_acceptable', $this->decodedResponse());

        $this->client->request('POST', '/api/warehouses', [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor($this->tenant, $this->role)], 'x');
        $this->assertProblem(415, 'shared.unsupported_media_type', $this->decodedResponse());
    }

    public function testTellsCachesThatTheAnswerDependsOnTheLanguage(): void
    {
        $this->request('GET', '/api/nope');

        self::assertStringContainsString('Accept-Language', (string) $this->header('Vary'));
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_ACCEPT_LANGUAGE' => '', 'HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor($this->tenant, $this->role)];
    }

    private function header(string $name): ?string
    {
        return $this->client->getResponse()->headers->get($name);
    }
}
