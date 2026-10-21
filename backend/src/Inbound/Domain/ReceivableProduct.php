<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

/**
 * What receiving needs to know about a product: whether it may be received at all (INB-01) and
 * whether each unit must carry a lot and an expiry date (INB-07). The application layer builds it
 * from the Catalog contract at the moment of the call.
 */
final readonly class ReceivableProduct
{
    public function __construct(
        public ProductId $id,
        public bool $available,
        public bool $trackedByLot,
        public bool $trackedByExpiry,
    ) {
    }
}
