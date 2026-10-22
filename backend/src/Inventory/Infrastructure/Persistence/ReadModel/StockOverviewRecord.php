<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\ReadModel;

/**
 * The row of the stock read model, one per stock item (ADR 0013). It is mapped only so that
 * Doctrine's schema tools, the migrations and the test database know the table: it is written and
 * read with plain SQL, by {@see DbalStockOverview}.
 */
final readonly class StockOverviewRecord
{
    public function __construct(
        public string $stockItemId,
        public string $tenantId,
        public string $productId,
        public string $sku,
        public string $productName,
        public string $warehouseId,
        public string $warehouseCode,
        public string $locationId,
        public string $locationCode,
        public ?string $lot,
        public ?\DateTimeImmutable $expiryDate,
        public string $status,
        public int $onHand,
    ) {
    }
}
