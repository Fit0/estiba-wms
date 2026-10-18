<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\Input;

use App\Shared\Domain\StorageClass;

final readonly class DefineZoneInput
{
    public function __construct(
        public string $code,
        public string $name,
        public StorageClass $storageClass,
        public int $pickSequence = 0,
    ) {
    }
}
