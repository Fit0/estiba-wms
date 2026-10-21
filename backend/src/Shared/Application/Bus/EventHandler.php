<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Marks a handler of a domain event: a policy, such as "whenever a receipt closes, book its
 * stock". It is invokable, takes one event and is registered on the event bus because of this
 * interface. An event can have several handlers.
 */
interface EventHandler
{
}
