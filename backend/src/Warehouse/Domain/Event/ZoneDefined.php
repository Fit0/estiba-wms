<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Event;

use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\StorageClass;
use App\Warehouse\Domain\WarehouseId;
use App\Warehouse\Domain\ZoneCode;
use App\Warehouse\Domain\ZoneId;

final readonly class ZoneDefined implements DomainEvent
{
    public function __construct(
        public WarehouseId $warehouseId,
        public ZoneId $zoneId,
        public ZoneCode $code,
        public StorageClass $storageClass,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
