<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Asks a question of its one handler. There is no transaction and no flush: reading never
 * changes state.
 *
 * A domain error thrown by the handler reaches the caller as it was thrown, not wrapped.
 */
interface QueryBus
{
    /**
     * @template TResult
     *
     * @param Query<TResult> $query
     *
     * @return TResult
     */
    public function ask(Query $query): mixed;
}
