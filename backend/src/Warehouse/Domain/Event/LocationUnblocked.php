<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Event;

use App\Shared\Domain\DomainEvent;
use App\Warehouse\Domain\LocationCode;
use App\Warehouse\Domain\LocationId;
use App\Warehouse\Domain\WarehouseId;

final readonly class LocationUnblocked implements DomainEvent
{
    public function __construct(
        public LocationId $locationId,
        public WarehouseId $warehouseId,
        public LocationCode $code,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
