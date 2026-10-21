<?php

declare(strict_types=1);

namespace App\Inbound\Application\RegisterAsn;

/**
 * One line of an ASN to register: a product of the catalog and the base units announced.
 */
final readonly class RegisterAsnLine
{
    public function __construct(
        public string $productId,
        public int $units,
        public ?string $lot = null,
        public ?\DateTimeImmutable $expiryDate = null,
    ) {
    }
}
