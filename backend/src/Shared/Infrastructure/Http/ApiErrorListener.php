<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use ApiPlatform\Metadata\Exception\HttpExceptionInterface as ApiPlatformHttpException;
use App\Shared\Application\TenantNotResolved;
use App\Shared\Domain\DomainError;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;

/**
 * Gives every error of the API the same shape: RFC 9457 `application/problem+json`, translated
 * into the language the request asks for (ADR 0004 and ADR 0007).
 *
 * - A broken domain rule keeps its own code and parameters.
 * - A request the serializer cannot read, a route that does not exist, a method or a format the
 *   API does not offer: a generic `shared.*` code, with the HTTP status it already had.
 * - Not signed in (401) and not allowed (403) are answered by the security layer, through the same
 *   {@see ProblemFactory}.
 * - Anything unexpected becomes `shared.internal_error`, so no internal detail leaves the API.
 *   While debugging (`dev` and `test`) it is left alone, so the stack trace stays visible.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final readonly class ApiErrorListener
{
    private const string API_PATH = '/api';

    public function __construct(
        private ProblemFactory $problems,
        private LoggerInterface $logger,
        #[Autowire(param: 'kernel.debug')]
        private bool $debug,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $error = $event->getThrowable();
        $request = $event->getRequest();

        if ($error instanceof DomainError) {
            $event->setResponse($this->problems->create($request, $this->statusFor($error->errorCode()), $error->errorCode(), $error->errorParameters(), $error->getMessage()));

            return;
        }

        // No authenticated user to take the tenant from: the caller is not signed in.
        if ($error instanceof TenantNotResolved) {
            $event->setResponse($this->problems->create($request, Response::HTTP_UNAUTHORIZED, HttpProblem::Unauthenticated->value, [], $error->getMessage()));

            return;
        }

        if (!$this->isApiRequest($request)) {
            return;
        }

        // The firewall answers these itself (401 through the JWT entry point, 403 through the
        // access denied handler), in the same problem shape.
        if ($error instanceof AuthenticationException || $error instanceof AccessDeniedException) {
            return;
        }

        $status = $this->httpStatus($error);
        if (null === $status) {
            $this->respondToUnexpected($event, $error);

            return;
        }

        $event->setResponse($this->problems->create($request, $status, HttpProblem::forStatus($status)->value, [], $error->getMessage()));
    }

    /**
     * The HTTP status of an error that is the client's doing, or null for anything else.
     */
    private function httpStatus(\Throwable $error): ?int
    {
        return match (true) {
            $error instanceof HttpExceptionInterface, $error instanceof ApiPlatformHttpException => $error->getStatusCode(),
            $error instanceof SerializerException => Response::HTTP_BAD_REQUEST,
            default => null,
        };
    }

    private function respondToUnexpected(ExceptionEvent $event, \Throwable $error): void
    {
        if ($this->debug) {
            return;
        }

        // Setting a response stops Symfony's own logging, so log it here.
        $this->logger->error('Unexpected error in the API.', ['exception' => $error]);
        $event->setResponse($this->problems->create($event->getRequest(), Response::HTTP_INTERNAL_SERVER_ERROR, HttpProblem::InternalError->value));
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), self::API_PATH);
    }

    private function statusFor(string $code): int
    {
        return match (true) {
            'shared.identifier_invalid' === $code => Response::HTTP_BAD_REQUEST,
            str_ends_with($code, '_not_found') => Response::HTTP_NOT_FOUND,
            str_contains($code, '_already_'),
            str_ends_with($code, '_not_blocked'),
            str_ends_with($code, '_not_disabled'),
            str_ends_with($code, '_discontinued') => Response::HTTP_CONFLICT,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };
    }
}
