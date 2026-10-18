<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

use App\Shared\Domain\StorageClass;

/**
 * A named area of a warehouse with one storage class and one place in the picking route.
 * A warehouse has few zones, so zones live inside the {@see Warehouse} aggregate.
 */
final readonly class Zone
{
    public function __construct(
        public ZoneId $id,
        public ZoneCode $code,
        public DisplayName $name,
        public StorageClass $storageClass,
        public PickSequence $pickSequence,
    ) {
    }
}
