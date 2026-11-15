<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Infrastructure\Http\HttpProblem;
use App\Shared\Infrastructure\Http\ProblemFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * Answers every authentication failure with the same translated problem shape as the rest of the
 * API, instead of LexikJWT's own JSON.
 *
 * Signing in with a wrong password, an unknown email or a disabled account all answer the same
 * way, so the answer does not tell whether the account exists.
 *
 * A sign-in that the limiter refuses (ADR 0053) is a 429 and not a 401, and says how long to wait in
 * `Retry-After`. The limit counts tries by email and by address, whether or not the account exists, so
 * the answer does not tell that either.
 */
final readonly class AuthenticationFailureListener
{
    public function __construct(
        private ProblemFactory $problems,
        private RequestStack $requests,
    ) {
    }

    #[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
    public function onCredentialsInvalid(AuthenticationFailureEvent $event): void
    {
        $exception = $event->getException();
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $event->setResponse($this->throttled($event, $exception));

            return;
        }

        $event->setResponse($this->unauthorized($event, HttpProblem::CredentialsInvalid));
    }

    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function onTokenMissing(AuthenticationFailureEvent $event): void
    {
        $event->setResponse($this->unauthorized($event, HttpProblem::Unauthenticated, bearer: true));
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    public function onTokenInvalid(AuthenticationFailureEvent $event): void
    {
        $event->setResponse($this->unauthorized($event, HttpProblem::TokenInvalid, bearer: true));
    }

    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onTokenExpired(AuthenticationFailureEvent $event): void
    {
        $event->setResponse($this->unauthorized($event, HttpProblem::TokenExpired, bearer: true));
    }

    private function throttled(AuthenticationFailureEvent $event, TooManyLoginAttemptsAuthenticationException $exception): JsonResponse
    {
        $request = $event->getRequest() ?? $this->requests->getCurrentRequest() ?? new Request();

        // The exception says how long to wait in whole minutes, rounded up.
        $minutes = $exception->getMessageData()['%minutes%'] ?? null;
        $seconds = \is_int($minutes) && $minutes > 0 ? $minutes * 60 : 60;

        $response = $this->problems->create($request, Response::HTTP_TOO_MANY_REQUESTS, HttpProblem::TooManyLoginAttempts->value, [], $exception->getMessage());
        $response->headers->set('Retry-After', (string) $seconds);

        return $response;
    }

    private function unauthorized(AuthenticationFailureEvent $event, HttpProblem $problem, bool $bearer = false): JsonResponse
    {
        $request = $event->getRequest() ?? $this->requests->getCurrentRequest() ?? new Request();

        $response = $this->problems->create($request, Response::HTTP_UNAUTHORIZED, $problem->value, [], $event->getException()->getMessage());
        if ($bearer) {
            // RFC 6750: tells the client which scheme to use.
            $response->headers->set('WWW-Authenticate', 'Bearer');
        }

        return $response;
    }
}
