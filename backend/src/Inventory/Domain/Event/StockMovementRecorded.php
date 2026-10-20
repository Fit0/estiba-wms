<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\MovementType;
use App\Inventory\Domain\StockItemId;
use App\Inventory\Domain\StockMovementId;
use App\Shared\Domain\DomainEvent;

final readonly class StockMovementRecorded implements DomainEvent
{
    public function __construct(
        public StockMovementId $movementId,
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
