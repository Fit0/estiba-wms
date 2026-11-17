<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testReportsEveryDependencyAsUp(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","checks":{"database":"up","redis":"up"}}',
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testLivenessAnswersWithoutLookingAtAnyDependency(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health/live');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', (string) $client->getResponse()->getContent());
    }
}
