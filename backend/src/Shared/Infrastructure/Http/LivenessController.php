<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Whether the process answers, and nothing else (E10-04, ADR 0062). It touches no database and no Redis, so a
 * platform that restarts a container when this fails does not restart it because PostgreSQL is slow. Whether
 * the application can do its work is `/health`.
 */
#[Route('/health/live', name: 'health_live', methods: ['GET'])]
final readonly class LivenessController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
