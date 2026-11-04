<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\ReadModel;

use App\Inventory\Application\Contract\StockKpis;
use App\Inventory\Application\Contract\StockSummary;
use App\Inventory\Domain\LocationId;
use App\Inventory\Domain\StockStatus;
use App\Shared\Domain\TenantId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Answers the {@see StockKpis} contract with plain SQL over `stock_overview` (ADR 0013). Every query
 * names the tenant itself, because the tenant filter of the ORM does not see plain SQL.
 */
final readonly class DbalStockKpis implements StockKpis
{
    /** Locations looked up per query, so a big warehouse does not build a statement of tens of thousands of parameters. */
    private const int CHUNK = 5000;

    public function __construct(private Connection $connection)
    {
    }

    #[\Override]
    public function summary(TenantId $tenantId): StockSummary
    {
        $row = $this->connection->executeQuery(
            'SELECT COALESCE(SUM(on_hand), 0) AS on_hand,
                    COALESCE(SUM(on_hand) FILTER (WHERE status = :available), 0) AS available,
                    COALESCE(SUM(on_hand) FILTER (WHERE status = :quarantine), 0) AS quarantine,
                    COALESCE(SUM(on_hand) FILTER (WHERE status = :damaged), 0) AS damaged,
                    COALESCE(SUM(on_hand) FILTER (WHERE status = :onHold), 0) AS on_hold
             FROM stock_overview
             WHERE tenant_id = :tenant AND on_hand > 0',
            [
                'tenant' => $tenantId->value,
                'available' => StockStatus::Available->value,
                'quarantine' => StockStatus::Quarantine->value,
                'damaged' => StockStatus::Damaged->value,
                'onHold' => StockStatus::OnHold->value,
            ],
        )->fetchAssociative();
        \assert(\is_array($row));

        return new StockSummary(
            $this->integer($row['on_hand']),
            $this->integer($row['available']),
            $this->integer($row['quarantine']),
            $this->integer($row['damaged']),
            $this->integer($row['on_hold']),
        );
    }

    #[\Override]
    public function occupiedAmong(TenantId $tenantId, array $locationIds): int
    {
        $occupied = 0;

        foreach (array_chunk($locationIds, self::CHUNK) as $chunk) {
            $occupied += $this->integer($this->connection->executeQuery(
                'SELECT COUNT(DISTINCT location_id) FROM stock_overview
                 WHERE tenant_id = :tenant AND on_hand > 0 AND location_id IN (:locations)',
                [
                    'tenant' => $tenantId->value,
                    'locations' => array_map(static fn (string $id): string => LocationId::fromString($id)->value, $chunk),
                ],
                ['locations' => ArrayParameterType::STRING],
            )->fetchOne());
        }

        return $occupied;
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
