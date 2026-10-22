<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\ReadModel;

use App\Inventory\Application\StockOverview\StockOverviewEntry;
use App\Inventory\Application\StockOverview\StockOverviewProjection;
use App\Inventory\Application\StockOverview\StockOverviewReader;
use App\Inventory\Application\StockOverview\StockSearch;
use App\Inventory\Application\StockOverview\StockTotals;
use App\Inventory\Application\StockOverview\WarehouseStock;
use App\Inventory\Domain\ProductId;
use App\Inventory\Domain\StockItemId;
use App\Inventory\Domain\StockStatus;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * The stock read model in the `stock_overview` table (ADR 0013), written and read with plain SQL:
 * there is no aggregate to load, only rows to show. Every query names the tenant itself, because
 * the tenant filter of the ORM does not see plain SQL.
 */
final readonly class DbalStockOverview implements StockOverviewProjection, StockOverviewReader
{
    private const string TABLE = 'stock_overview';

    public function __construct(private Connection $connection)
    {
    }

    #[\Override]
    public function save(StockOverviewEntry $entry): void
    {
        $this->connection->executeStatement(
            'INSERT INTO stock_overview (stock_item_id, tenant_id, product_id, sku, product_name, warehouse_id, warehouse_code, location_id, location_code, lot, expiry_date, status, on_hand)
             VALUES (:stockItemId, :tenantId, :productId, :sku, :productName, :warehouseId, :warehouseCode, :locationId, :locationCode, :lot, :expiryDate, :status, :onHand)
             ON CONFLICT (stock_item_id) DO UPDATE SET
                 sku = EXCLUDED.sku, product_name = EXCLUDED.product_name,
                 warehouse_id = EXCLUDED.warehouse_id, warehouse_code = EXCLUDED.warehouse_code,
                 location_code = EXCLUDED.location_code, on_hand = EXCLUDED.on_hand',
            [
                'stockItemId' => $entry->stockItemId,
                'tenantId' => $entry->tenantId->value,
                'productId' => $entry->productId,
                'sku' => $entry->sku,
                'productName' => $entry->productName,
                'warehouseId' => $entry->warehouseId,
                'warehouseCode' => $entry->warehouseCode,
                'locationId' => $entry->locationId,
                'locationCode' => $entry->locationCode,
                'lot' => $entry->lot,
                'expiryDate' => $entry->expiryDate?->format('Y-m-d'),
                'status' => $entry->status->value,
                'onHand' => $entry->onHand,
            ],
        );
    }

    #[\Override]
    public function clear(): void
    {
        // DELETE and not TRUNCATE: readers keep seeing the old rows until the rebuild commits.
        $this->connection->executeStatement('DELETE FROM stock_overview');
    }

    #[\Override]
    public function search(TenantId $tenantId, StockSearch $search, PageRequest $page): Page
    {
        $query = $this->stockOf($tenantId)->andWhere('on_hand > 0');
        $this->filter($query, 'product_id', $search->productId?->value);
        $this->filter($query, 'location_id', $search->locationId?->value);
        $this->filter($query, 'warehouse_id', $search->warehouseId?->value);
        $this->filter($query, 'status', $search->status?->value);

        $total = $this->count($query);

        $rows = $query->select('*')
            ->orderBy('sku')
            ->addOrderBy('location_code')
            ->addOrderBy('expiry_date')
            ->addOrderBy('lot')
            ->addOrderBy('status')
            ->setFirstResult($page->offset())
            ->setMaxResults($page->itemsPerPage)
            ->executeQuery()
            ->fetchAllAssociative();

        return new Page(array_map($this->entryOf(...), $rows), $total, $page);
    }

    #[\Override]
    public function find(TenantId $tenantId, StockItemId $id): ?StockOverviewEntry
    {
        $row = $this->stockOf($tenantId)
            ->select('*')
            ->andWhere('stock_item_id = :id')
            ->setParameter('id', $id->value)
            ->executeQuery()
            ->fetchAssociative();

        return false === $row ? null : $this->entryOf($row);
    }

    #[\Override]
    public function stockOfProduct(TenantId $tenantId, ProductId $productId): array
    {
        $rows = $this->connection->executeQuery(
            'SELECT warehouse_id, warehouse_code,
                    SUM(on_hand) AS on_hand,
                    COALESCE(SUM(on_hand) FILTER (WHERE status = :available), 0) AS available,
                    COALESCE(SUM(on_hand) FILTER (WHERE status = :quarantine), 0) AS quarantine,
                    COALESCE(SUM(on_hand) FILTER (WHERE status = :damaged), 0) AS damaged,
                    COALESCE(SUM(on_hand) FILTER (WHERE status = :onHold), 0) AS on_hold,
                    COUNT(DISTINCT location_id) AS locations
             FROM stock_overview
             WHERE tenant_id = :tenant AND product_id = :product AND on_hand > 0
             GROUP BY warehouse_id, warehouse_code
             ORDER BY warehouse_code',
            [
                'tenant' => $tenantId->value,
                'product' => $productId->value,
                'available' => StockStatus::Available->value,
                'quarantine' => StockStatus::Quarantine->value,
                'damaged' => StockStatus::Damaged->value,
                'onHold' => StockStatus::OnHold->value,
            ],
        )->fetchAllAssociative();

        return array_map($this->warehouseStockOf(...), $rows);
    }

    private function stockOf(TenantId $tenantId): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->from(self::TABLE)
            ->where('tenant_id = :tenant')
            ->setParameter('tenant', $tenantId->value);
    }

    private function count(QueryBuilder $query): int
    {
        $total = (clone $query)->select('COUNT(*)')->executeQuery()->fetchOne();

        return is_numeric($total) ? (int) $total : 0;
    }

    private function filter(QueryBuilder $query, string $column, ?string $value): void
    {
        if (null !== $value) {
            $query->andWhere($column.' = :'.$column)->setParameter($column, $value);
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    private function entryOf(array $values): StockOverviewEntry
    {
        $row = new Row($values, $this->connection->getDatabasePlatform());

        return new StockOverviewEntry(
            $row->string('stock_item_id'),
            TenantId::fromString($row->string('tenant_id')),
            $row->string('product_id'),
            $row->string('sku'),
            $row->string('product_name'),
            $row->string('warehouse_id'),
            $row->string('warehouse_code'),
            $row->string('location_id'),
            $row->string('location_code'),
            $row->nullableString('lot'),
            $row->nullableDate('expiry_date'),
            StockStatus::from($row->string('status')),
            $row->int('on_hand'),
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function warehouseStockOf(array $values): WarehouseStock
    {
        $row = new Row($values, $this->connection->getDatabasePlatform());

        return new WarehouseStock(
            $row->string('warehouse_id'),
            $row->string('warehouse_code'),
            new StockTotals($row->int('on_hand'), $row->int('available'), $row->int('quarantine'), $row->int('damaged'), $row->int('on_hold')),
            $row->int('locations'),
        );
    }
}
