<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * E10-04: every answer says which request it was, and a caller that sends its own identifier gets it back.
 */
final class RequestIdTest extends WebTestCase
{
    public function testEveryAnswerCarriesTheIdentifierOfTheRequest(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health/live');

        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', (string) $client->getResponse()->headers->get('X-Request-Id'));
    }

    public function testEachRequestHasItsOwn(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health/live');
        $first = $client->getResponse()->headers->get('X-Request-Id');
        $client->request('GET', '/health/live');

        self::assertNotSame($first, $client->getResponse()->headers->get('X-Request-Id'));
    }

    public function testGivesBackTheIdentifierTheCallerSent(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health/live', server: ['HTTP_X_REQUEST_ID' => 'billing-7f3a9c21']);

        self::assertResponseHeaderSame('X-Request-Id', 'billing-7f3a9c21');
    }

    public function testReplacesAnIdentifierThatIsNotAcceptable(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health/live', server: ['HTTP_X_REQUEST_ID' => 'not acceptable']);

        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', (string) $client->getResponse()->headers->get('X-Request-Id'));
    }

    public function testErrorAnswersCarryItToo(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/products');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHasHeader('X-Request-Id');
    }
}
