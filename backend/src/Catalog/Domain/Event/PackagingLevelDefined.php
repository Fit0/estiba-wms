<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\PackagingLevel;
use App\Catalog\Domain\ProductId;
use App\Shared\Domain\DomainEvent;

final readonly class PackagingLevelDefined implements DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public PackagingLevel $level,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
