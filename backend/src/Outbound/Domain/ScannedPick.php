<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * What the operator scanned to confirm a pick: the location label, the barcode of the product, and
 * the lot when the product has one. The product is already looked up, so `productId` is null when
 * the barcode belongs to nothing the tenant knows (OUT-11).
 */
final readonly class ScannedPick
{
    public function __construct(
        public string $locationCode,
        public ?ProductId $productId,
        public ?string $lot,
    ) {
    }
}
