<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\ReadModel;

use App\Outbound\Application\Contract\OutboundFigures;
use App\Outbound\Application\Contract\OutboundKpis;
use App\Outbound\Domain\OrderStatus;
use App\Outbound\Domain\PickListStatus;
use App\Outbound\Domain\ShipmentStatus;
use App\Shared\Domain\TenantId;
use Doctrine\DBAL\Connection;

/**
 * Answers the {@see OutboundKpis} contract with plain SQL over the tables of Outbound itself. The
 * stops of a pick list are a JSON column, so they are unfolded in the query. Every query names the
 * tenant, because the tenant filter of the ORM does not see plain SQL.
 */
final readonly class DbalOutboundKpis implements OutboundKpis
{
    public function __construct(private Connection $connection)
    {
    }

    #[\Override]
    public function between(TenantId $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $until): OutboundFigures
    {
        // With the microseconds: the columns keep whole seconds, so an event of the second that is
        // passing now is before an `until` of "now" and not equal to it.
        $period = [
            'tenant' => $tenantId->value,
            'from' => $from->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            'until' => $until->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        ];

        $orders = $this->connection->executeQuery(
            'SELECT COUNT(*) FILTER (WHERE status NOT IN (:shipped, :cancelled)) AS open,
                    COUNT(*) FILTER (WHERE registered_at >= :from AND registered_at < :until) AS registered
             FROM orders
             WHERE tenant_id = :tenant',
            $period + ['shipped' => OrderStatus::Shipped->value, 'cancelled' => OrderStatus::Cancelled->value],
        )->fetchAssociative();

        $shipped = $this->connection->executeQuery(
            'SELECT COUNT(*) FROM shipments
             WHERE tenant_id = :tenant AND status = :dispatched AND dispatched_at >= :from AND dispatched_at < :until',
            $period + ['dispatched' => ShipmentStatus::Dispatched->value],
        )->fetchOne();

        $lists = $this->connection->executeQuery(
            'SELECT COUNT(*) AS lists,
                    COALESCE(SUM(EXTRACT(EPOCH FROM (completed_at - started_at))) FILTER (WHERE started_at IS NOT NULL), 0) AS seconds
             FROM pick_lists
             WHERE tenant_id = :tenant AND status = :completed AND completed_at >= :from AND completed_at < :until',
            $period + ['completed' => PickListStatus::Completed->value],
        )->fetchAssociative();

        $stops = $this->connection->executeQuery(
            'SELECT COUNT(*) AS lines,
                    COALESCE(SUM((stop->>\'quantity_picked\')::int), 0) AS picked,
                    COUNT(*) FILTER (WHERE (stop->>\'quantity_picked\')::int < (stop->>\'quantity_to_pick\')::int) AS short_lines,
                    COALESCE(SUM((stop->>\'quantity_to_pick\')::int - (stop->>\'quantity_picked\')::int)
                        FILTER (WHERE (stop->>\'quantity_picked\')::int < (stop->>\'quantity_to_pick\')::int), 0) AS short_units
             FROM pick_lists
             CROSS JOIN LATERAL json_array_elements(pick_lists.lines::json) AS stop
             WHERE tenant_id = :tenant AND status = :completed AND completed_at >= :from AND completed_at < :until
               AND (stop->>\'confirmed\')::boolean',
            $period + ['completed' => PickListStatus::Completed->value],
        )->fetchAssociative();
        \assert(\is_array($orders) && \is_array($lists) && \is_array($stops));

        return new OutboundFigures(
            $this->integer($orders['open']),
            $this->integer($orders['registered']),
            $this->integer($shipped),
            $this->integer($lists['lists']),
            $this->integer($stops['lines']),
            $this->integer($stops['picked']),
            $this->integer($stops['short_lines']),
            $this->integer($stops['short_units']),
            $this->integer($lists['seconds']),
        );
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) round((float) $value) : 0;
    }
}
