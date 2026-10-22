<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Inventory\Application\StockOverview\StockOverviewEntry;
use App\Inventory\Domain\StockStatus;
use App\Inventory\Infrastructure\Http\State\StockItemProvider;

/**
 * The stock of one product in one location, of one lot, expiry date and status, from the read
 * model (ADR 0013): it shows a movement a moment after it is recorded. Dates are days, as
 * `YYYY-MM-DD`.
 *
 * The list holds the stock items with units, filtered by `productId`, `locationId`, `warehouseId`
 * and `status`.
 */
#[ApiResource(
    shortName: 'StockItem',
    operations: [
        new GetCollection(uriTemplate: '/stock-items', provider: StockItemProvider::class),
        new Get(uriTemplate: '/stock-items/{id}', provider: StockItemProvider::class),
    ],
)]
final readonly class StockItemResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $productId,
        public string $sku,
        public string $productName,
        public string $warehouseId,
        public string $warehouseCode,
        public string $locationId,
        public string $locationCode,
        public ?string $lot,
        public ?string $expiryDate,
        public StockStatus $status,
        public int $onHand,
    ) {
    }

    public static function fromEntry(StockOverviewEntry $entry): self
    {
        return new self(
            $entry->stockItemId,
            $entry->productId,
            $entry->sku,
            $entry->productName,
            $entry->warehouseId,
            $entry->warehouseCode,
            $entry->locationId,
            $entry->locationCode,
            $entry->lot,
            $entry->expiryDate?->format('Y-m-d'),
            $entry->status,
            $entry->onHand,
        );
    }
}
