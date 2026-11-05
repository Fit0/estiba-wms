<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Realtime;

use App\Shared\Domain\TenantId;

/**
 * The Mercure topic of the notifications of a tenant. Nobody else can subscribe to it: the token of a
 * subscriber names this topic and no other (ADR 0045).
 */
final readonly class NotificationTopic
{
    public static function of(TenantId $tenantId): string
    {
        return \sprintf('/tenants/%s/notifications', $tenantId->value);
    }
}
