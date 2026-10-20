<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Adds who the token is for to its payload, so a client can show the user and the tenant without
 * another request. The server does not trust these claims: it loads the user from the database on
 * every request (ADR 0008).
 */
#[AsEventListener(event: Events::JWT_CREATED)]
final readonly class JwtCreatedListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof SecurityUser) {
            return;
        }

        $event->setData($event->getData() + [
            'userId' => $user->id->value,
            'tenantId' => $user->tenantId->value,
        ]);
    }
}
