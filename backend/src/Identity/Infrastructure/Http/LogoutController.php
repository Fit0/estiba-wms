<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use App\Identity\Application\RevokeRefreshToken\RevokeRefreshToken;
use App\Identity\Infrastructure\Security\RefreshCookie;
use App\Shared\Application\Bus\CommandBus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Ends the session: the refresh token of the cookie and its whole family stop working, and the
 * cookie is cleared. It answers the same when there was no session, so it never fails.
 */
#[Route('/api/auth/logout', name: 'identity_logout', methods: ['POST'])]
final readonly class LogoutController
{
    public function __construct(
        private CommandBus $commandBus,
        private RefreshCookie $cookie,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $secret = $this->cookie->secretOf($request);
        if ('' !== $secret) {
            $this->commandBus->dispatch(new RevokeRefreshToken($secret));
        }

        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookie->clear($request));

        return $response;
    }
}
