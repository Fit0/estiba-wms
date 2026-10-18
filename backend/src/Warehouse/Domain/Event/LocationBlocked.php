<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Event;

use App\Shared\Domain\DomainEvent;
use App\Warehouse\Domain\BlockReason;
use App\Warehouse\Domain\LocationCode;
use App\Warehouse\Domain\LocationId;
use App\Warehouse\Domain\WarehouseId;

final readonly class LocationBlocked implements DomainEvent
{
    public function __construct(
        public LocationId $locationId,
        public WarehouseId $warehouseId,
        public LocationCode $code,
        public BlockReason $reason,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
