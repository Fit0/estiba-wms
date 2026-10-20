<?php

declare(strict_types=1);

namespace App\Catalog\Application\AssignGtin;

use App\Catalog\Domain\Product;
use App\Shared\Application\Bus\Command;

/**
 * @implements Command<Product>
 */
final readonly class AssignGtin implements Command
{
    public function __construct(
        public string $productId,
        public string $gtin,
    ) {
    }
}
