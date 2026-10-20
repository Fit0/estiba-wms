<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Runs a command through its one handler, inside a database transaction that is committed when
 * the handler returns and rolled back when it throws.
 *
 * A domain error thrown by the handler reaches the caller as it was thrown, not wrapped.
 */
interface CommandBus
{
    /**
     * @template TResult
     *
     * @param Command<TResult> $command
     *
     * @return TResult
     */
    public function dispatch(Command $command): mixed;
}
