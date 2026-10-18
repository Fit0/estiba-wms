<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Event;

use App\Shared\Domain\DomainEvent;
use App\Warehouse\Domain\LocationCode;
use App\Warehouse\Domain\LocationId;
use App\Warehouse\Domain\LocationType;
use App\Warehouse\Domain\WarehouseId;
use App\Warehouse\Domain\ZoneId;

final readonly class LocationCreated implements DomainEvent
{
    public function __construct(
        public LocationId $locationId,
        public WarehouseId $warehouseId,
        public ZoneId $zoneId,
        public LocationCode $code,
        public LocationType $type,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
