<?php

declare(strict_types=1);

namespace App\Catalog\Application\ChangeProductStatus;

use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductStatus;
use App\Shared\Application\Bus\Command;

/**
 * Moves a product to a status: blocked blocks it, active unblocks it and discontinued
 * discontinues it.
 *
 * @implements Command<Product>
 */
final readonly class ChangeProductStatus implements Command
{
    public function __construct(
        public string $productId,
        public ProductStatus $status,
    ) {
    }
}
