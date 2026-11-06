<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\TenantId;

/**
 * Tells the signed-in people of a tenant that something happened, as it happens (ADR 0045). It is a
 * best effort: a person who is not connected learns it from the screens, which always show the truth.
 * A policy that fails to notify is retried like any other.
 */
interface Notifier
{
    /**
     * @throws \RuntimeException when the notification could not be delivered to the hub
     */
    public function notify(TenantId $tenantId, Notification $notification): void;
}
