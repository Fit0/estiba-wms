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

    final protected function record(DomainEvent $event): void
    {
        $this->events[] = $event;
    }
}
