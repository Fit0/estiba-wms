<?php

declare(strict_types=1);

namespace App\Warehouse\Domain\Event;

use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;
use App\Warehouse\Domain\WarehouseCode;
use App\Warehouse\Domain\WarehouseId;

final readonly class WarehouseCreated implements DomainEvent
{
    public function __construct(
        public WarehouseId $warehouseId,
        public TenantId $tenantId,
        public WarehouseCode $code,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
