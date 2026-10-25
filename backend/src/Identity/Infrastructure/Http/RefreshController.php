<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use App\Identity\Application\RotateRefreshToken\RotatedRefreshToken;
use App\Identity\Application\RotateRefreshToken\RotateRefreshToken;
use App\Identity\Infrastructure\Security\DoctrineUserProvider;
use App\Identity\Infrastructure\Security\RefreshCookie;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\HttpProblem;
use App\Shared\Infrastructure\Http\ProblemFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Renews the session: the refresh token that the browser sends in its cookie is exchanged for a new
 * one, and the answer carries a new access token (ADR 0022). Whatever is wrong with the token, the
 * answer is the same, and the cookie is cleared.
 */
#[Route('/api/auth/refresh', name: 'identity_refresh', methods: ['POST'])]
final readonly class RefreshController
{
    public function __construct(
        private CommandBus $commandBus,
        private DoctrineUserProvider $users,
        private JWTTokenManagerInterface $jwt,
        private RefreshCookie $cookie,
        private ProblemFactory $problems,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $secret = $this->cookie->secretOf($request);
        $rotated = '' === $secret ? null : $this->commandBus->dispatch(new RotateRefreshToken($secret));

        if (!$rotated instanceof RotatedRefreshToken) {
            return $this->refused($request);
        }

        $response = new JsonResponse(['token' => $this->jwt->create($this->users->loadUserByIdentifier($rotated->email))]);
        $response->headers->setCookie($this->cookie->issue($rotated->refreshToken, $request));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function refused(Request $request): JsonResponse
    {
        $response = $this->problems->create($request, Response::HTTP_UNAUTHORIZED, HttpProblem::RefreshTokenInvalid->value, [], 'The session cannot be renewed. Sign in again.');
        $response->headers->setCookie($this->cookie->clear($request));

        return $response;
    }
}
