<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\DomainEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Events published while a command runs wait until the command's transaction commits: a command
 * that fails publishes nothing. Then each event goes where `config/packages/messenger.yaml` routes
 * it: to the asynchronous transport, where a worker handles it with retries, or straight to its
 * handlers. Either way each handler runs in a transaction of its own (ADR 0012).
 */
final readonly class MessengerEventBus implements EventBus
{
    public function __construct(
        #[Autowire(service: 'event.bus')]
        private MessageBusInterface $bus,
    ) {
    }

    #[\Override]
    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            try {
                $this->bus->dispatch(new Envelope($event, [new DispatchAfterCurrentBusStamp()]));
            } catch (HandlerFailedException|DelayedMessageHandlingException $failure) {
                throw HandledResult::originalOf($failure);
            }
        }
    }
}
