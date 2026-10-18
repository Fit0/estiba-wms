<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\Resource;

use App\Catalog\Domain\PackagingLevel;
use App\Catalog\Domain\PackagingType;

/**
 * A packaging level as it appears inside a product.
 */
final readonly class PackagingLevelResource
{
    public function __construct(
        public PackagingType $type,
        public int $baseUnits,
    ) {
    }

    public static function fromDomain(PackagingLevel $level): self
    {
        return new self($level->type, $level->baseUnits);
    }
}
