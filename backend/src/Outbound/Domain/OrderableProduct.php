<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * What the order needs to know about a product, as the catalog said when the order was made.
 */
final readonly class OrderableProduct
{
    public function __construct(
        public ProductId $id,
        public bool $available,
    ) {
    }
}
