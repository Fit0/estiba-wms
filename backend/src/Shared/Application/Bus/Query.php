<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * A request to read state, named as a question (`ListProducts`). It goes through the
 * {@see QueryBus} and must not change anything.
 *
 * @template-covariant TResult of mixed what the handler answers
 */
interface Query
{
}
