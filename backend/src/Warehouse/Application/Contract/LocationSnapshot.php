<?php

declare(strict_types=1);

namespace App\Warehouse\Application\Contract;

use App\Shared\Domain\ProductFamily;
use App\Shared\Domain\StorageClass;

/**
 * What another context may know about a location and its zone, at the moment it asked. It is a
 * copy: changing the location later does not change it.
 *
 * `type` is the value of the location type (`reserve`, `pick_face`, `dock`, `staging`, `packing`,
 * `quarantine`). `active` says whether putaway and allocation may use it (WH-06). The storage class
 * and the families are those of its zone. A null limit means there is none.
 */
final readonly class LocationSnapshot
{
    public const string TYPE_DOCK = 'dock';

    public const string TYPE_RESERVE = 'reserve';

    public const string TYPE_PICK_FACE = 'pick_face';

    public const string TYPE_QUARANTINE = 'quarantine';

    /**
     * @param list<ProductFamily> $families
     */
    public function __construct(
        public string $id,
        public string $warehouseId,
        public string $zoneId,
        public string $code,
        public string $type,
        public bool $active,
        public StorageClass $storageClass,
        public array $families,
        public int $zonePickSequence,
        public int $pickSequence,
        public ?int $maxWeightKg,
        public ?int $maxVolumeLitres,
    ) {
    }

    /**
     * Where goods arrive or leave.
     */
    public function isDock(): bool
    {
        return self::TYPE_DOCK === $this->type;
    }
}
