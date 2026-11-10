<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Erp\Application\OutboxItem;
use App\Erp\Application\ShipmentConfirmation;
use App\Erp\Application\ShipmentOutbox;
use App\Shared\Domain\TenantId;

/**
 * An outbox in memory, for the tests of what uses it. It keeps what happened to each shipment.
 */
final class InMemoryShipmentOutbox implements ShipmentOutbox
{
    /** @var array<string, OutboxRecord> */
    public array $rows = [];

    #[\Override]
    public function enqueue(TenantId $tenantId, string $shipmentId, ShipmentConfirmation $confirmation, \DateTimeImmutable $at): void
    {
        $this->rows[$shipmentId] ??= new OutboxRecord($tenantId, $confirmation, 'pending', 0, $at);
    }

    #[\Override]
    public function due(\DateTimeImmutable $now, int $limit): array
    {
        $items = [];
        foreach ($this->rows as $shipmentId => $row) {
            if ('pending' === $row->status && $row->next <= $now) {
                $items[] = new OutboxItem($shipmentId, $row->tenant, $row->confirmation, $row->attempts);
            }
        }

        return \array_slice($items, 0, $limit);
    }

    #[\Override]
    public function markSent(string $shipmentId, \DateTimeImmutable $at): void
    {
        $this->rows[$shipmentId]->status = 'sent';
    }

    #[\Override]
    public function scheduleRetry(string $shipmentId, int $attempts, \DateTimeImmutable $nextAttemptAt, string $reason): void
    {
        $this->rows[$shipmentId]->attempts = $attempts;
        $this->rows[$shipmentId]->next = $nextAttemptAt;
        $this->rows[$shipmentId]->error = $reason;
    }

    #[\Override]
    public function markFailed(string $shipmentId, int $attempts, string $reason): void
    {
        $this->rows[$shipmentId]->status = 'failed';
        $this->rows[$shipmentId]->attempts = $attempts;
        $this->rows[$shipmentId]->error = $reason;
    }
}
