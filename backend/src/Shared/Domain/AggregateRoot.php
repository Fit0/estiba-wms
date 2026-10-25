<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Collects the events an aggregate raises while it changes. The application layer
 * takes them with {@see pullEvents()} after saving and publishes them.
 */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $events = [];

    /**
     * @return list<DomainEvent>
     */
    final public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    /**
     * Whether the aggregate raised events that nobody has taken yet, without taking them.
     */
    final public function hasPendingEvents(): bool
    {
        return [] !== $this->events;
    }

    /**
     * The classes of the events that nobody has taken yet, without taking them.
     *
     * @return list<class-string<DomainEvent>>
     */
    final public function pendingEventClasses(): array
    {
        return array_map(static fn (DomainEvent $event): string => $event::class, $this->events);
    }

    final protected function record(DomainEvent $event): void
    {
        $this->events[] = $event;
    }
}
