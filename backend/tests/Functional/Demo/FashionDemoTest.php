<?php

declare(strict_types=1);

namespace App\Tests\Functional\Demo;

use App\Demo\Fashion\FashionDemoLoader;
use App\Identity\Infrastructure\Console\PasswordPrompt;
use App\Tests\Functional\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The demo company, loaded the way a developer loads it and then used through the API as each of
 * its three users would.
 */
final class FashionDemoTest extends ApiTestCase
{
    private const string PASSWORD = 'demo-password-2027';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER[PasswordPrompt::ENVIRONMENT_VARIABLE] = self::PASSWORD;
        $this->load();
    }

    #[\Override]
    protected function tearDown(): void
    {
        unset($_SERVER[PasswordPrompt::ENVIRONMENT_VARIABLE]);

        parent::tearDown();
    }

    public function testLoadingTwiceChangesNothing(): void
    {
        $again = $this->load();

        self::assertSame(0, $again->getStatusCode(), $again->getDisplay());
        self::assertStringContainsString('already loaded', $again->getDisplay());
        self::assertCount(39, $this->listedAs(FashionDemoLoader::OPERATOR_EMAIL, '/api/products', ['itemsPerPage' => 100]));
    }

    public function testNeverPrintsThePassword(): void
    {
        self::assertStringNotContainsString(self::PASSWORD, $this->load()->getDisplay());
    }

    public function testEachDemoUserSignsInWithTheirRole(): void
    {
        foreach ([
            FashionDemoLoader::ADMIN_EMAIL => 'admin',
            FashionDemoLoader::SUPERVISOR_EMAIL => 'supervisor',
            FashionDemoLoader::OPERATOR_EMAIL => 'operator',
        ] as $email => $role) {
            $me = $this->requestAs($email, 'GET', '/api/me');

            $this->assertStatus(200);
            self::assertSame($email, $me['email']);
            self::assertSame([$role], $me['roles']);
        }
    }

    public function testTheOperatorSeesTheWholeDistributionCentre(): void
    {
        $warehouses = $this->listedAs(FashionDemoLoader::OPERATOR_EMAIL, '/api/warehouses');
        self::assertCount(1, $warehouses);
        self::assertSame('ZGZ1', $warehouses[0]['code'] ?? null);
        self::assertIsArray($warehouses[0]['zones'] ?? null);
        self::assertCount(6, $warehouses[0]['zones']);

        $warehouse = $this->id($warehouses[0]);
        self::assertCount(42, $this->listedAs(FashionDemoLoader::OPERATOR_EMAIL, \sprintf('/api/warehouses/%s/locations', $warehouse), ['itemsPerPage' => 100]));
        self::assertCount(39, $this->listedAs(FashionDemoLoader::OPERATOR_EMAIL, '/api/products', ['itemsPerPage' => 100]));
    }

    public function testOnlyASupervisorMayChangeTheCatalog(): void
    {
        $this->requestAs(FashionDemoLoader::OPERATOR_EMAIL, 'POST', '/api/products', []);
        $this->assertStatus(403);

        // An empty body is refused for being invalid, not for lacking rights.
        $this->requestAs(FashionDemoLoader::SUPERVISOR_EMAIL, 'POST', '/api/products', []);
        self::assertNotSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testAnotherTenantSeesNothingOfTheDemo(): void
    {
        $this->request('GET', '/api/products');
        $this->assertStatus(200);
        self::assertSame([], $this->decodedResponse());

        $this->request('GET', '/api/warehouses');
        self::assertSame([], $this->decodedResponse());
    }

    private function load(): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new \LogicException('The kernel is not booted.'));
        $tester = new CommandTester($application->find('app:demo:load'));
        $tester->execute([], ['interactive' => false]);

        return $tester;
    }

    /**
     * @param array<string, int|string> $query
     *
     * @return list<array<array-key, mixed>>
     */
    private function listedAs(string $email, string $uri, array $query = []): array
    {
        $items = $this->requestAs($email, 'GET', $uri, null, $query);
        $this->assertStatus(200);

        return array_values(array_filter($items, \is_array(...)));
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, int|string> $query
     *
     * @return array<array-key, mixed>
     */
    private function requestAs(string $email, string $method, string $uri, ?array $body = null, array $query = []): array
    {
        $this->client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], json_encode(['email' => $email, 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR));
        $this->assertStatus(200);
        $token = $this->decodedResponse()['token'] ?? null;
        self::assertIsString($token);

        $this->client->request($method, $uri, $query, [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ], null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));

        return $this->decodedResponse();
    }
}
