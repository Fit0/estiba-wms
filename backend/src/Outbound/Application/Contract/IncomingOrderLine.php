<?php

declare(strict_types=1);

namespace App\Outbound\Application\Contract;

/**
 * One line of an order that comes from outside: a product of the catalogue, by its id, and the units.
 */
final readonly class IncomingOrderLine
{
    public function __construct(
        public string $productId,
        public int $units,
    ) {
    }
}
