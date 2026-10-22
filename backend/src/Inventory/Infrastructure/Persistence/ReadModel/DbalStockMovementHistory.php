<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\ReadModel;

use App\Inventory\Application\StockOverview\MovementSearch;
use App\Inventory\Application\StockOverview\StockMovementEntry;
use App\Inventory\Application\StockOverview\StockMovementHistory;
use App\Inventory\Domain\MovementType;
use App\Inventory\Domain\StockStatus;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Reads the ledger, `stock_movements`, joined to the read model for the product and location of
 * each stock item. Both tables belong to Inventory, so the join stays inside the context (ADR 0003).
 */
final readonly class DbalStockMovementHistory implements StockMovementHistory
{
    public function __construct(private Connection $connection)
    {
    }

    #[\Override]
    public function search(TenantId $tenantId, MovementSearch $search, PageRequest $page): Page
    {
        $query = $this->connection->createQueryBuilder()
            ->from('stock_movements', 'm')
            ->innerJoin('m', 'stock_overview', 'o', 'o.stock_item_id = m.stock_item_id')
            ->where('m.tenant_id = :tenant')
            ->setParameter('tenant', $tenantId->value);
        $this->filter($query, 'm.stock_item_id', $search->stockItemId?->value);
        $this->filter($query, 'o.product_id', $search->productId?->value);
        $this->filter($query, 'o.location_id', $search->locationId?->value);

        $total = $this->count($query);

        $rows = $query
            ->select('m.id', 'm.stock_item_id', 'o.product_id', 'o.sku', 'o.location_id', 'o.location_code', 'o.lot', 'o.expiry_date', 'o.status')
            ->addSelect('m.type', 'm.change', 'm.balance_after', 'm.reference', 'm.reason', 'm.author_id', 'm.occurred_at')
            ->orderBy('m.occurred_at', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setFirstResult($page->offset())
            ->setMaxResults($page->itemsPerPage)
            ->executeQuery()
            ->fetchAllAssociative();

        return new Page(array_map($this->entryOf(...), $rows), $total, $page);
    }

    private function count(QueryBuilder $query): int
    {
        $total = (clone $query)->select('COUNT(*)')->executeQuery()->fetchOne();

        return is_numeric($total) ? (int) $total : 0;
    }

    private function filter(QueryBuilder $query, string $column, ?string $value): void
    {
        if (null !== $value) {
            $parameter = str_replace('.', '_', $column);
            $query->andWhere($column.' = :'.$parameter)->setParameter($parameter, $value);
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    private function entryOf(array $values): StockMovementEntry
    {
        $row = new Row($values, $this->connection->getDatabasePlatform());

        return new StockMovementEntry(
            $row->string('id'),
            $row->string('stock_item_id'),
            $row->string('product_id'),
            $row->string('sku'),
            $row->string('location_id'),
            $row->string('location_code'),
            $row->nullableString('lot'),
            $row->nullableDate('expiry_date'),
            StockStatus::from($row->string('status')),
            MovementType::from($row->string('type')),
            $row->int('change'),
            $row->int('balance_after'),
            $row->nullableString('reference'),
            $row->nullableString('reason'),
            $row->string('author_id'),
            $row->dateTime('occurred_at'),
        );
    }
}
