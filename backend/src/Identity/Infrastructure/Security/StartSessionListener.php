<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\IssueRefreshToken\IssueRefreshToken;
use App\Shared\Application\Bus\CommandBus;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Signing in also starts a session: the answer carries the refresh token in its cookie, so the
 * client can renew the access token without asking for the password again (ADR 0022).
 */
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
final readonly class StartSessionListener
{
    public function __construct(
        private CommandBus $commandBus,
        private RefreshCookie $cookie,
        private RequestStack $requests,
    ) {
    }

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $this->requests->getCurrentRequest();

        if (!$user instanceof SecurityUser || !$request instanceof Request) {
            return;
        }

        $refreshToken = $this->commandBus->dispatch(new IssueRefreshToken($user->id->value));

        $event->getResponse()->headers->setCookie($this->cookie->issue($refreshToken, $request));
    }
}
