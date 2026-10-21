<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

use App\Shared\Domain\DomainEvent;

/**
 * Tells the rest of the application what happened. Every {@see EventHandler} of an event runs, in
 * the same process and, when the event is published from a command, inside the command's
 * transaction: what the handlers do is committed or rolled back with the command (ADR 0011).
 *
 * An event nobody handles is fine. A domain error thrown by a handler reaches the publisher as it
 * was thrown, not wrapped.
 */
interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
