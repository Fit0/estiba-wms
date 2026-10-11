<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liveness and dependency check used by Docker, CI and the deployment platform.
 */
#[Route('/health', name: 'health', methods: ['GET'])]
final readonly class HealthController
{
    public function __construct(
        private Connection $connection,
        #[Autowire(env: 'REDIS_URL')]
        private string $redisUrl,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn (): mixed => $this->connection->executeQuery('SELECT 1')->fetchOne()),
            'redis' => $this->check($this->pingRedis(...)),
        ];

        $healthy = !\in_array('down', $checks, true);

        return new JsonResponse(
            ['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks],
            $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    /**
     * @param callable(): mixed $probe
     */
    private function check(callable $probe): string
    {
        try {
            $probe();

            return 'up';
        } catch (\Throwable) {
            return 'down';
        }
    }

    private function pingRedis(): void
    {
        $url = parse_url($this->redisUrl);
        if (false === $url) {
            throw new \InvalidArgumentException('REDIS_URL is not a valid URL.');
        }

        $redis = new \Redis();
        $redis->connect($url['host'] ?? 'localhost', $url['port'] ?? 6379, 1.0);
        $redis->ping();
        $redis->close();
    }
}
