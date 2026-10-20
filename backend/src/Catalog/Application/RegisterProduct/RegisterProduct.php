<?php

declare(strict_types=1);

namespace App\Catalog\Application\RegisterProduct;

use App\Catalog\Domain\Product;
use App\Catalog\Domain\UnitOfMeasure;
use App\Shared\Application\Bus\Command;
use App\Shared\Domain\StorageClass;

/**
 * @implements Command<Product>
 */
final readonly class RegisterProduct implements Command
{
    public function __construct(
        public string $sku,
        public string $name,
        public UnitOfMeasure $baseUnit,
        public StorageClass $storageClass,
        public int $lengthMm,
        public int $widthMm,
        public int $heightMm,
        public int $weightGrams,
        public bool $trackedByLot = false,
        public bool $trackedByExpiry = false,
        public ?int $minShelfLifeAtReceiptDays = null,
        public ?int $minShelfLifeAtShipmentDays = null,
    ) {
    }
}
