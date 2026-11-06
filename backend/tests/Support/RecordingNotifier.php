<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Application\Notification;
use App\Shared\Application\Notifier;
use App\Shared\Domain\TenantId;

/**
 * The notifier of the functional tests: there is no hub, so it remembers what it was asked to say. The
 * list is static because the test reads it from outside the container, which only has this service in
 * the test environment: call {@see reset()} in `setUp()`.
 */
final class RecordingNotifier implements Notifier
{
    /** @var list<array{tenant: string, notification: Notification}> */
    private static array $sent = [];

    #[\Override]
    public function notify(TenantId $tenantId, Notification $notification): void
    {
        self::$sent[] = ['tenant' => $tenantId->value, 'notification' => $notification];
    }

    /**
     * @return list<array{tenant: string, notification: Notification}>
     */
    public static function sent(): array
    {
        return self::$sent;
    }

    public static function reset(): void
    {
        self::$sent = [];
    }
}
