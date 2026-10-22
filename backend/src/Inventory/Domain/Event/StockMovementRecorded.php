<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\MovementType;
use App\Inventory\Domain\StockItemId;
use App\Inventory\Domain\StockMovementId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

/**
 * The units of a stock item changed. It is handled on the worker, where no user is signed in, so it
 * says whose stock it is (ADR 0012).
 */
final readonly class StockMovementRecorded implements DomainEvent
{
    public function __construct(
        public StockMovementId $movementId,
        public TenantId $tenantId,
        public StockItemId $stockItemId,
        public MovementType $type,
        public int $change,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
