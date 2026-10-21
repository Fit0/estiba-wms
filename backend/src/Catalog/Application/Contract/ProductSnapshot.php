<?php

declare(strict_types=1);

namespace App\Catalog\Application\Contract;

use App\Shared\Domain\StorageClass;

/**
 * What another context may know about a product, at the moment it asked. It is a copy: changing
 * the product later does not change it.
 *
 * `availableForOperations` says whether the product may be received or allocated: it is neither
 * blocked nor discontinued (CAT-06).
 */
final readonly class ProductSnapshot
{
    public function __construct(
        public string $id,
        public string $sku,
        public StorageClass $storageClass,
        public bool $trackedByLot,
        public bool $trackedByExpiry,
        public ?int $minShelfLifeAtReceiptDays,
        public bool $availableForOperations,
    ) {
    }
}
