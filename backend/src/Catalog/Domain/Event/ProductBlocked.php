<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ProductId;
use App\Catalog\Domain\Sku;
use App\Shared\Domain\DomainEvent;

final readonly class ProductBlocked implements DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public Sku $sku,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
