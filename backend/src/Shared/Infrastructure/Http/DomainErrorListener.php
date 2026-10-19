<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Application\TenantNotResolved;
use App\Shared\Domain\DomainError;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Turns a broken domain rule into an RFC 9457 `application/problem+json` response.
 *
 * `code` and `parameters` are stable, so a client can pick its own text; `detail` is the
 * English message until error translation (E02-05) replaces it.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final readonly class DomainErrorListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $error = $event->getThrowable();

        if ($error instanceof DomainError) {
            $event->setResponse($this->problem($this->statusFor($error->errorCode()), $error->errorCode(), $error->getMessage(), $error->errorParameters()));
        } elseif ($error instanceof TenantNotResolved) {
            $event->setResponse($this->problem(Response::HTTP_UNAUTHORIZED, 'shared.tenant_missing', $error->getMessage(), []));
        }
    }

    private function statusFor(string $code): int
    {
        return match (true) {
            'shared.identifier_invalid' === $code => Response::HTTP_BAD_REQUEST,
            str_ends_with($code, '_not_found') => Response::HTTP_NOT_FOUND,
            str_contains($code, '_already_'),
            str_ends_with($code, '_not_blocked'),
            str_ends_with($code, '_discontinued') => Response::HTTP_CONFLICT,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };
    }

    /**
     * @param array<string, bool|float|int|string> $parameters
     */
    private function problem(int $status, string $code, string $detail, array $parameters): JsonResponse
    {
        $response = new JsonResponse([
            'type' => 'urn:estiba:error:'.$code,
            'title' => Response::$statusTexts[$status],
            'status' => $status,
            'code' => $code,
            'detail' => $detail,
            'parameters' => (object) $parameters,
        ], $status);
        $response->headers->set('Content-Type', 'application/problem+json');

        return $response;
    }
}
