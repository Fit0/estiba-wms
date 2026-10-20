<?php

declare(strict_types=1);

namespace App\Warehouse\Application\DefineZone;

use App\Shared\Application\Bus\Command;
use App\Shared\Domain\StorageClass;
use App\Warehouse\Domain\Warehouse;

/**
 * @implements Command<Warehouse>
 */
final readonly class DefineZone implements Command
{
    public function __construct(
        public string $warehouseId,
        public string $code,
        public string $name,
        public StorageClass $storageClass,
        public int $pickSequence,
    ) {
    }
}
