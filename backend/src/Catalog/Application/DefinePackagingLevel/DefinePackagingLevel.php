<?php

declare(strict_types=1);

namespace App\Catalog\Application\DefinePackagingLevel;

use App\Catalog\Domain\PackagingType;

final readonly class DefinePackagingLevel
{
    public function __construct(
        public string $productId,
        public PackagingType $type,
        public int $baseUnits,
    ) {
    }
}
