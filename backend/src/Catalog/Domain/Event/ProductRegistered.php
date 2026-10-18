<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ProductId;
use App\Catalog\Domain\Sku;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\StorageClass;
use App\Shared\Domain\TenantId;

final readonly class ProductRegistered implements DomainEvent
{
    public function __construct(
        public ProductId $productId,
        public TenantId $tenantId,
        public Sku $sku,
        public StorageClass $storageClass,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
