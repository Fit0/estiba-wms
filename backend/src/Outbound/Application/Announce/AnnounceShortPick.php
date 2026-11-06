<?php

declare(strict_types=1);

namespace App\Outbound\Application\Announce;

use App\Outbound\Domain\Event\ShortPickReported;
use App\Shared\Application\Bus\EventHandler;
use App\Shared\Application\Notification;
use App\Shared\Application\Notifier;

/**
 * Whenever an operator reports units they did not find, the people connected hear it (ADR 0045). The
 * id of the notification is the stop of the list, so a second delivery is the same notification.
 */
final readonly class AnnounceShortPick implements EventHandler
{
    public function __construct(private Notifier $notifier)
    {
    }

    public function __invoke(ShortPickReported $event): void
    {
        $this->notifier->notify($event->tenantId, new Notification(
            \sprintf('short-pick:%s:%d', $event->pickListId->value, $event->position),
            'outbound.short_pick_reported',
            [
                'pickListId' => $event->pickListId->value,
                'position' => $event->position,
                'orderId' => $event->orderId->value,
                'missingUnits' => $event->missingUnits,
            ],
        ));
    }
}
