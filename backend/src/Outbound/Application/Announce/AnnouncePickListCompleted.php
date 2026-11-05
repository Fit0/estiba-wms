<?php

declare(strict_types=1);

namespace App\Outbound\Application\Announce;

use App\Outbound\Domain\Event\PickListCompleted;
use App\Shared\Application\Bus\EventHandler;
use App\Shared\Application\Notification;
use App\Shared\Application\Notifier;

/**
 * Whenever a pick list is completed, the people connected hear it (ADR 0045). The worker handles it
 * a moment after the last stop commits. It needs no mark of its own to be idempotent: the id of the
 * notification is the pick list, so a second delivery is the same notification.
 */
final readonly class AnnouncePickListCompleted implements EventHandler
{
    public function __construct(private Notifier $notifier)
    {
    }

    public function __invoke(PickListCompleted $event): void
    {
        $this->notifier->notify($event->tenantId, new Notification(
            'pick-list-completed:'.$event->pickListId->value,
            'outbound.pick_list_completed',
            ['pickListId' => $event->pickListId->value, 'waveId' => $event->waveId->value],
        ));
    }
}
