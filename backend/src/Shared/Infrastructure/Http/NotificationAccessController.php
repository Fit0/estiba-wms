<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Infrastructure\Realtime\NotificationTopic;
use App\Shared\Infrastructure\Realtime\SubscriberToken;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lets a signed-in supervisor listen to the notifications of the tenant. The answer names the topic and
 * the hub, and sets the `mercureAuthorization` cookie that the hub reads: `HttpOnly`, so the page cannot
 * read it, and only sent to the hub's path (ADR 0045). It is not part of the JSON resources because its
 * point is the cookie.
 */
#[Route('/api/notifications/access', name: 'notification_access', methods: ['GET'])]
final readonly class NotificationAccessController
{
    public const string HUB_PATH = '/.well-known/mercure';

    public const string COOKIE = 'mercureAuthorization';

    public function __construct(
        private CurrentTenant $tenant,
        private Clock $clock,
        private SubscriberToken $tokens,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $now = $this->clock->now();
        $tenantId = $this->tenant->id();
        $expires = $now->modify(\sprintf('+%d seconds', SubscriberToken::LIFETIME_SECONDS));

        $response = new JsonResponse([
            'topic' => NotificationTopic::of($tenantId),
            'hub' => self::HUB_PATH,
            'expiresAt' => $expires->format(\DATE_ATOM),
        ]);
        $response->headers->setCookie(Cookie::create(self::COOKIE)
            ->withValue($this->tokens->forTenant($tenantId, $now))
            ->withExpires($expires)
            ->withPath(self::HUB_PATH)
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
