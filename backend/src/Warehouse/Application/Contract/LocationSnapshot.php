<?php

declare(strict_types=1);

namespace App\Warehouse\Application\Contract;

/**
 * What another context may know about a location, at the moment it asked. It is a copy: changing
 * the location later does not change it.
 *
 * `type` is the value of the location type (`reserve`, `pick_face`, `dock`, `staging`, `packing`,
 * `quarantine`). `active` says whether putaway and allocation may use it (WH-06).
 */
final readonly class LocationSnapshot
{
    public const string TYPE_DOCK = 'dock';

    public function __construct(
        public string $id,
        public string $warehouseId,
        public string $zoneId,
        public string $code,
        public string $type,
        public bool $active,
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
