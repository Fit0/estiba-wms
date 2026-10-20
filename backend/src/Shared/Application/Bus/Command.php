<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * A request to change state, named as an order (`RegisterProduct`). It goes through the
 * {@see CommandBus}, which runs it in one transaction.
 *
 * @template-covariant TResult of mixed what the handler hands back to the caller
 */
interface Command
{
}
