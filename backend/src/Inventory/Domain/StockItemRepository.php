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
     * The stock items of the tenant in these locations that hold at least one unit.
     *
     * @param list<LocationId> $locationIds
     *
     * @return list<StockItem>
     */
    public function findAt(TenantId $tenantId, array $locationIds): array;

    /**
     * The stock items of the product that are available and have units nobody has promised.
     * Whether their location is usable is for the caller to find out (STK-06).
     *
     * @return list<StockItem>
     */
    public function findReservable(TenantId $tenantId, ProductId $productId): array;

    /**
     * Every stock item of every tenant, for work that goes over the whole ledger, such as building
     * a read model again.
     *
     * @return iterable<StockItem>
     */
    public function all(): iterable;

    /**
     * Saves the stock item and, with it, the movements it recorded since it was loaded, so the
     * change and its movement are never apart (STK-03). The command bus owns the transaction.
     */
    public function save(StockItem $stockItem): void;
}
