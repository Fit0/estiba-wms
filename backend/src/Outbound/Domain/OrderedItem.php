<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * What a customer asks for, before it becomes a line of the order: a product and its units.
 */
final readonly class OrderedItem
{
    public function __construct(
        public OrderableProduct $product,
        public int $units,
    ) {
    }
}
