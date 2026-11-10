<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Persistence;

use App\Erp\Application\OutboxItem;
use App\Erp\Application\ShipmentConfirmation;
use App\Erp\Application\ShipmentOutbox;
use App\Shared\Domain\TenantId;
use Doctrine\DBAL\Connection;

/**
 * The outbox in one table, `erp_shipment_outbox`, with one row for each shipment (ADR 0049). The shipment is the key, so
 * queueing it twice is one row.
 */
final readonly class DbalShipmentOutbox implements ShipmentOutbox
{
    private const string PENDING = 'pending';

    private const string SENT = 'sent';

    private const string FAILED = 'failed';

    public function __construct(private Connection $connection)
    {
    }

    #[\Override]
    public function enqueue(TenantId $tenantId, string $shipmentId, ShipmentConfirmation $confirmation, \DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO erp_shipment_outbox (shipment_id, tenant_id, order_reference, payload, status, attempts, next_attempt_at, created_at) '
            .'VALUES (?, ?, ?, ?, ?, 0, ?, ?) ON CONFLICT (shipment_id) DO NOTHING',
            [$shipmentId, $tenantId->value, $confirmation->orderReference, ConfirmationPayload::encode($confirmation), self::PENDING, Utc::text($at), Utc::text($at)],
        );
    }

    #[\Override]
    public function due(\DateTimeImmutable $now, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT shipment_id, tenant_id, payload, attempts FROM erp_shipment_outbox '
            .'WHERE status = ? AND next_attempt_at <= ? ORDER BY next_attempt_at, created_at LIMIT '.max(1, $limit),
            [self::PENDING, Utc::text($now)],
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
            [self::SENT, Utc::text($at), $shipmentId],
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
            [self::FAILED, $attempts, $reason, $shipmentId],
        );
    }
}
