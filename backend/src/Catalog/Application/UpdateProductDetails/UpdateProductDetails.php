<?php

declare(strict_types=1);

namespace App\Catalog\Application\UpdateProductDetails;

/**
 * Changes only what is given. The tracking policy, the base unit and the storage class are
 * fixed at registration (CAT-05) and are not here.
 */
final readonly class UpdateProductDetails
{
    public function __construct(
        public string $productId,
        public ?string $name = null,
        public ?int $lengthMm = null,
        public ?int $widthMm = null,
        public ?int $heightMm = null,
        public ?int $weightGrams = null,
    ) {
    }

    public function changesDimensions(): bool
    {
        return null !== $this->lengthMm || null !== $this->widthMm || null !== $this->heightMm || null !== $this->weightGrams;
    }
}
