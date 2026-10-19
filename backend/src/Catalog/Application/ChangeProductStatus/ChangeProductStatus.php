<?php

declare(strict_types=1);

namespace App\Catalog\Application\ChangeProductStatus;

use App\Catalog\Domain\ProductStatus;

/**
 * Moves a product to a status: blocked blocks it, active unblocks it and discontinued
 * discontinues it.
 */
final readonly class ChangeProductStatus
{
    public function __construct(
        public string $productId,
        public ProductStatus $status,
    ) {
    }
}
