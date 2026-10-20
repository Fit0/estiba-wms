<?php

declare(strict_types=1);

namespace App\Demo\Fashion;

use App\Catalog\Domain\UnitOfMeasure;

/**
 * One product of the demo catalog: a garment in one colour and one size, which is how fashion is
 * stocked. Every size and colour has its own SKU and its own barcode.
 */
final readonly class DemoProduct
{
    public function __construct(
        public string $sku,
        public string $name,
        public UnitOfMeasure $unit,
        public int $lengthMm,
        public int $widthMm,
        public int $heightMm,
        public int $weightGrams,
        public string $gtin,
        public int $unitsPerCase,
        public int $unitsPerPallet,
    ) {
    }
}
