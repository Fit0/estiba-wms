<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure\Http\Resource;

/**
 * `open` is the orders not shipped or cancelled right now. `registered` and `shipped` are those of
 * the period.
 */
final readonly class OrderKpisResource
{
    public function __construct(
        public int $open,
        public int $registered,
        public int $shipped,
    ) {
    }
}
