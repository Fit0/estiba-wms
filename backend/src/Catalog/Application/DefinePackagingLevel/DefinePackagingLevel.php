<?php

declare(strict_types=1);

namespace App\Catalog\Application\DefinePackagingLevel;

use App\Catalog\Domain\PackagingType;
use App\Catalog\Domain\Product;
use App\Shared\Application\Bus\Command;

/**
 * @implements Command<Product>
 */
final readonly class DefinePackagingLevel implements Command
{
    public function __construct(
        public string $productId,
        public PackagingType $type,
        public int $baseUnits,
    ) {
    }
}
