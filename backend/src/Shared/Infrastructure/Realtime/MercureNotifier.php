<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Realtime;

use App\Shared\Application\Clock;
use App\Shared\Application\Notification;
use App\Shared\Application\Notifier;
use App\Shared\Domain\TenantId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Publishes a notification to the Mercure hub as a private update on the topic of the tenant, so only
 * the people of that tenant who hold a subscriber token receive it (ADR 0045). The id of the update is
 * the id of the notification.
 */
final readonly class MercureNotifier implements Notifier
{
    private const int TOKEN_SECONDS = 60;

    public function __construct(
        private HubTransport $transport,
        private Clock $clock,
        #[Autowire(env: 'MERCURE_URL')]
        private string $hubUrl,
        #[Autowire(env: 'MERCURE_PUBLISHER_JWT_KEY')]
        private string $publisherKey,
    ) {
    }

    #[\Override]
    public function notify(TenantId $tenantId, Notification $notification): void
    {
        $token = HmacJwt::sign(
            ['mercure' => ['publish' => [NotificationTopic::of($tenantId)]], 'exp' => $this->clock->now()->getTimestamp() + self::TOKEN_SECONDS],
            $this->publisherKey,
        );

        $this->transport->publish($this->hubUrl, [
            'topic' => NotificationTopic::of($tenantId),
            'data' => json_encode(['type' => $notification->type, 'data' => $notification->data], \JSON_THROW_ON_ERROR),
            'private' => 'on',
            'id' => $notification->id,
        ], $token);
    }
}
