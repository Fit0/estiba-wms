<?php

declare(strict_types=1);

namespace App\Inventory\Application;

use App\Inventory\Application\Contract\StockAtLocation;
use App\Inventory\Application\Contract\StockLevels;
use App\Inventory\Domain\LocationId;
use App\Inventory\Domain\StockItemRepository;
use App\Shared\Domain\TenantId;

/**
 * Answers the {@see StockLevels} contract from the stock items.
 */
final readonly class LedgerStockLevels implements StockLevels
{
    public function __construct(private StockItemRepository $stockItems)
    {
    }

    #[\Override]
    public function at(TenantId $tenantId, array $locationIds): array
    {
        $units = [];
        foreach ($this->stockItems->findAt($tenantId, array_map(LocationId::fromString(...), $locationIds)) as $stockItem) {
            $key = $stockItem->key();
            $index = $key->locationId->value.'|'.$key->productId->value;
            $units[$index] = new StockAtLocation(
                $key->locationId->value,
                $key->productId->value,
                ($units[$index]->units ?? 0) + $stockItem->onHand()->units,
            );
        }

        return array_values($units);
    }
}
