<?php

declare(strict_types=1);

namespace App\Inventory\Application\StockOverview;

use App\Inventory\Domain\StockStatus;
use App\Shared\Domain\TenantId;

/**
 * One stock item as the read model holds it: its units, with the SKU and name of its product and
 * the codes of its location and warehouse, copied from Catalog and Warehouse when it was projected
 * (ADR 0013). No lot and no expiry date are null here, never the values the ledger stores for them.
 */
final readonly class StockOverviewEntry
{
    public function __construct(
        public string $stockItemId,
        public TenantId $tenantId,
        public string $productId,
        public string $sku,
        public string $productName,
        public string $warehouseId,
        public string $warehouseCode,
        public string $locationId,
        public string $locationCode,
        public ?string $lot,
        public ?\DateTimeImmutable $expiryDate,
        public StockStatus $status,
        public int $onHand,
    ) {
    }
}
