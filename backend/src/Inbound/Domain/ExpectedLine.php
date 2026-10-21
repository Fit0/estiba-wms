<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

/**
 * A line of an ASN as it is announced, before the ASN checks it: the product, how many base
 * units are coming and, when the supplier knows them, the lot and the expiry date.
 */
final readonly class ExpectedLine
{
    public ?\DateTimeImmutable $expiryDate;

    public function __construct(
        public ReceivableProduct $product,
        public int $units,
        public ?LotNumber $lot = null,
        ?\DateTimeImmutable $expiryDate = null,
    ) {
        $this->expiryDate = $expiryDate?->setTime(0, 0);
    }
}
