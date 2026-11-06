<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Realtime;

use App\Shared\Domain\TenantId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The token a browser shows the hub to listen to the notifications of its tenant: it may subscribe to
 * that topic and to nothing else, and it lasts as long as an access token (ADR 0045).
 */
final readonly class SubscriberToken
{
    public const int LIFETIME_SECONDS = 3600;

    public function __construct(
        #[Autowire(env: 'MERCURE_SUBSCRIBER_JWT_KEY')]
        private string $subscriberKey,
    ) {
    }

    public function forTenant(TenantId $tenantId, \DateTimeImmutable $now): string
    {
        return HmacJwt::sign(
            ['mercure' => ['subscribe' => [NotificationTopic::of($tenantId)]], 'exp' => $now->getTimestamp() + self::LIFETIME_SECONDS],
            $this->subscriberKey,
        );
    }
}
