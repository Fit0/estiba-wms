<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Tests\Functional\Support\ApiTestCase;

/**
 * E10-06 (ADR 0057): what the API tells a cache about the answers of a signed-in user. They are
 * private to that user and are not stored by a shared cache.
 */
final class ApiCacheControlTest extends ApiTestCase
{
    public function testAListOfASignedInUserIsPrivate(): void
    {
        $this->request('GET', '/api/warehouses');

        $this->assertStatus(200);
        $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringNotContainsString('public', $cacheControl);
    }

    public function testNoAnswerOfTheApiAsksACacheToKeepIt(): void
    {
        $this->request('GET', '/api/me');

        $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');
        self::assertStringNotContainsString('max-age', $cacheControl);
        self::assertStringNotContainsString('s-maxage', $cacheControl);
    }
}
