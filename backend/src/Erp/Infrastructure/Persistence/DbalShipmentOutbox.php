<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Persistence;

use App\Erp\Application\OutboxEntry;
use App\Erp\Application\OutboxItem;
use App\Erp\Application\OutboxStatus;
use App\Erp\Application\ShipmentConfirmation;
use App\Erp\Application\ShipmentOutbox;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use Doctrine\DBAL\Connection;

/**
 * The outbox in one table, `erp_shipment_outbox`, with one row for each shipment (ADR 0049). The shipment is the key, so
 * queueing it twice is one row.
 */
final readonly class DbalShipmentOutbox implements ShipmentOutbox
{
    public function __construct(private Connection $connection)
    {
    }

    #[\Override]
    public function enqueue(TenantId $tenantId, string $shipmentId, ShipmentConfirmation $confirmation, \DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO erp_shipment_outbox (shipment_id, tenant_id, order_reference, payload, status, attempts, next_attempt_at, created_at) '
            .'VALUES (?, ?, ?, ?, ?, 0, ?, ?) ON CONFLICT (shipment_id) DO NOTHING',
            [$shipmentId, $tenantId->value, $confirmation->orderReference, ConfirmationPayload::encode($confirmation), OutboxStatus::Pending->value, Utc::text($at), Utc::text($at)],
        );
    }

    #[\Override]
    public function due(\DateTimeImmutable $now, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT shipment_id, tenant_id, payload, attempts FROM erp_shipment_outbox '
            .'WHERE status = ? AND next_attempt_at <= ? ORDER BY next_attempt_at, created_at LIMIT '.max(1, $limit),
            [OutboxStatus::Pending->value, Utc::text($now)],
        );

        $items = [];
        foreach ($rows as $row) {
            if (!\is_string($row['shipment_id']) || !\is_string($row['tenant_id']) || !\is_string($row['payload']) || !\is_int($row['attempts'])) {
                throw new \UnexpectedValueException('A row of the outbox of the ERP does not have the columns it should.');
            }

            $items[] = new OutboxItem($row['shipment_id'], TenantId::fromString($row['tenant_id']), ConfirmationPayload::decode($row['payload']), $row['attempts']);
        }

        return $items;
    }

    #[\Override]
    public function markSent(string $shipmentId, \DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'UPDATE erp_shipment_outbox SET status = ?, sent_at = ?, last_error = NULL WHERE shipment_id = ?',
            [OutboxStatus::Sent->value, Utc::text($at), $shipmentId],
        );
    }

    #[\Override]
    public function scheduleRetry(string $shipmentId, int $attempts, \DateTimeImmutable $nextAttemptAt, string $reason): void
    {
        $this->connection->executeStatement(
            'UPDATE erp_shipment_outbox SET attempts = ?, next_attempt_at = ?, last_error = ? WHERE shipment_id = ?',
            [$attempts, Utc::text($nextAttemptAt), $reason, $shipmentId],
        );
    }

    #[\Override]
    public function markFailed(string $shipmentId, int $attempts, string $reason): void
    {
        $this->connection->executeStatement(
            'UPDATE erp_shipment_outbox SET status = ?, attempts = ?, last_error = ? WHERE shipment_id = ?',
            [OutboxStatus::Failed->value, $attempts, $reason, $shipmentId],
        );
    }

    #[\Override]
    public function search(TenantId $tenantId, ?OutboxStatus $status, PageRequest $page): Page
    {
        $where = 'tenant_id = ?';
        $parameters = [$tenantId->value];
        if ($status instanceof OutboxStatus) {
            $where .= ' AND status = ?';
            $parameters[] = $status->value;
        }

        $total = $this->connection->fetchOne('SELECT COUNT(*) FROM erp_shipment_outbox WHERE '.$where, $parameters);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT shipment_id, payload, status, attempts, next_attempt_at, last_error, created_at, sent_at FROM erp_shipment_outbox WHERE '.$where
            .' ORDER BY created_at DESC, shipment_id LIMIT '.$page->itemsPerPage.' OFFSET '.$page->offset(),
            $parameters,
        );

        return new Page(array_map($this->entry(...), $rows), \is_int($total) ? $total : 0, $page);
    }

    #[\Override]
    public function retry(TenantId $tenantId, string $shipmentId, \DateTimeImmutable $at): ?OutboxEntry
    {
        $changed = $this->connection->executeStatement(
            'UPDATE erp_shipment_outbox SET status = ?, attempts = 0, next_attempt_at = ?, last_error = NULL WHERE shipment_id = ? AND tenant_id = ? AND status = ?',
            [OutboxStatus::Pending->value, Utc::text($at), $shipmentId, $tenantId->value, OutboxStatus::Failed->value],
        );
        if (0 === $changed) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT shipment_id, payload, status, attempts, next_attempt_at, last_error, created_at, sent_at FROM erp_shipment_outbox WHERE shipment_id = ?',
            [$shipmentId],
        );

        return false === $row ? null : $this->entry($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function entry(array $row): OutboxEntry
    {
        if (!\is_string($row['shipment_id']) || !\is_string($row['payload']) || !\is_string($row['status']) || !\is_int($row['attempts'])
            || (null !== $row['last_error'] && !\is_string($row['last_error']))) {
            throw new \UnexpectedValueException('A row of the outbox of the ERP does not have the columns it should.');
        }

        return new OutboxEntry(
            $row['shipment_id'],
            ConfirmationPayload::decode($row['payload']),
            OutboxStatus::from($row['status']),
            $row['attempts'],
            Utc::parse($row['next_attempt_at']),
            $row['last_error'],
            Utc::parse($row['created_at']),
            null === $row['sent_at'] ? null : Utc::parse($row['sent_at']),
        );
    }
}
