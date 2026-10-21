<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Event;

use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\ProductFamily;
use App\Warehouse\Domain\WarehouseId;
use App\Warehouse\Domain\ZoneId;

final readonly class ZoneFamiliesAssigned implements DomainEvent
{
    /**
     * @param list<ProductFamily> $families
     */
    public function __construct(
        public WarehouseId $warehouseId,
        public ZoneId $zoneId,
        public array $families,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
