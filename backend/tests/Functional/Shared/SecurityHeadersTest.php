<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Shared\Infrastructure\Http\SecurityHeadersListener;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * E10-05: the headers every answer of the API carries (ADR 0053).
 */
final class SecurityHeadersTest extends WebTestCase
{
    private KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testAnswersOfTheApiCarryTheSecurityHeaders(): void
    {
        $this->client->request('GET', '/health');

        foreach (SecurityHeadersListener::HEADERS as $name => $value) {
            self::assertResponseHeaderSame($name, $value);
        }
        self::assertResponseHeaderSame('Content-Security-Policy', SecurityHeadersListener::CONTENT_SECURITY_POLICY);
    }

    public function testTheLivenessAnswerCarriesThemToo(): void
    {
        $this->client->request('GET', '/health/live');

        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Content-Security-Policy', SecurityHeadersListener::CONTENT_SECURITY_POLICY);
    }

    public function testErrorAnswersCarryTheSecurityHeadersToo(): void
    {
        // No token: the firewall answers with a 401 problem, not the endpoint.
        $this->client->request('GET', '/api/products');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Content-Security-Policy', SecurityHeadersListener::CONTENT_SECURITY_POLICY);
    }

    public function testTheDocumentationPageHasNoContentPolicy(): void
    {
        $this->client->request('GET', '/api/docs');

        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseNotHasHeader('Content-Security-Policy');
    }

    public function testStrictTransportSecurityIsOnlySentOverHttps(): void
    {
        $this->client->request('GET', '/health');
        self::assertResponseNotHasHeader('Strict-Transport-Security');

        $this->client->request('GET', '/health', server: ['HTTPS' => 'on']);
        self::assertResponseHeaderSame('Strict-Transport-Security', SecurityHeadersListener::STRICT_TRANSPORT_SECURITY);
    }
}
