<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Shared\Domain\TenantId;

/**
 * There is no delete method, and none for movements: stock is a ledger (STK-04).
 */
interface StockItemRepository
{
    public function find(StockItemId $id): ?StockItem;

    public function findByKey(TenantId $tenantId, StockKey $key): ?StockItem;

    /**
     * Saves the stock item and, with it, the movements it recorded since it was loaded, so the
     * change and its movement are never apart (STK-03). The command bus owns the transaction.
     */
    public function save(StockItem $stockItem): void;
}
