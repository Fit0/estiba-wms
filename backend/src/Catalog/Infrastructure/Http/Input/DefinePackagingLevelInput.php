<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\Input;

use App\Catalog\Domain\PackagingType;

final readonly class DefinePackagingLevelInput
{
    public function __construct(
        public PackagingType $type,
        public int $baseUnits,
    ) {
    }
}
