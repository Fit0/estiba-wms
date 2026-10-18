<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Exception\InvalidPackagingLevel;

/**
 * A larger pack of the base unit, such as a case of 12 or a pallet of 480.
 * It holds a positive whole number of base units (CAT-03).
 */
final readonly class PackagingLevel
{
    private function __construct(
        public PackagingType $type,
        public int $baseUnits,
    ) {
    }

    public static function of(PackagingType $type, int $baseUnits): self
    {
        if ($baseUnits < 1) {
            throw InvalidPackagingLevel::forBaseUnits($type, $baseUnits);
        }

        return new self($type, $baseUnits);
    }
}
