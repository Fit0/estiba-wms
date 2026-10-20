<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Marks the handler of a command. It is invokable, takes one {@see Command} and is registered on
 * the command bus because of this interface, so no framework attribute is needed.
 */
interface CommandHandler
{
}
