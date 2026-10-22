<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

use App\Shared\Domain\DomainEvent;

/**
 * Tells the rest of the application what happened. An event published while a command or another
 * event is handled leaves once that work commits, and is dropped if it fails. Then it goes to the
 * asynchronous transport when it is routed there, or straight to its handlers, each in a
 * transaction of its own (ADR 0012).
 *
 * An event nobody handles is fine. A domain error thrown by a handler reaches the publisher as it
 * was thrown, not wrapped.
 */
interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
