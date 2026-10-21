<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

/**
 * What a receipt expects of one product: the units of the ASN line still to come when the
 * receipt was opened. A product with nothing left to come is kept with zero, so receiving it is
 * an excess and not an unexpected product.
 */
final readonly class ExpectedItem
{
    public function __construct(
        public ProductId $productId,
        public int $units,
    ) {
    }
}
