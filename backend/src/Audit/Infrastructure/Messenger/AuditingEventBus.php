<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Messenger;

use App\Audit\Application\AuditTrail;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\DomainEvent;
use App\Shared\Infrastructure\Messenger\MessengerEventBus;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Wraps the event bus so that every event is written to the audit trail at the moment it is
 * published, in the transaction of whoever publishes it, and then goes on its way as before.
 */
#[AsDecorator(decorates: MessengerEventBus::class)]
final readonly class AuditingEventBus implements EventBus
{
    public function __construct(
        #[AutowireDecorated]
        private EventBus $inner,
        private AuditTrail $trail,
    ) {
    }

    #[\Override]
    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->trail->record($event);
        }

        $this->inner->publish(...$events);
    }
}
