<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Marks the handler of a query. It is invokable, takes one {@see Query} and is registered on the
 * query bus because of this interface.
 */
interface QueryHandler
{
}
