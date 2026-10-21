<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\DomainEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

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
                $this->bus->dispatch($event);
            } catch (HandlerFailedException $failure) {
                // The policies speak in domain errors, like the use cases: throw the first one as it was.
                throw array_values($failure->getWrappedExceptions())[0] ?? $failure;
            }
        }
    }
}
