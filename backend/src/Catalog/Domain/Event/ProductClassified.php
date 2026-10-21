<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ProductId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\ProductFamily;
use App\Shared\Domain\TurnoverClass;

final readonly class ProductClassified implements DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public ?ProductFamily $family,
        public TurnoverClass $turnoverClass,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
