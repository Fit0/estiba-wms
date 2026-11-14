<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Erp\Application\OutboxEntry;
use App\Erp\Application\OutboxItem;
use App\Erp\Application\OutboxStatus;
use App\Erp\Application\ShipmentConfirmation;
use App\Erp\Application\ShipmentOutbox;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
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

    #[\Override]
    public function search(TenantId $tenantId, ?OutboxStatus $status, PageRequest $page): Page
    {
        $entries = [];
        foreach (array_reverse($this->rows, true) as $shipmentId => $row) {
            if ($row->tenant->equals($tenantId) && (!$status instanceof OutboxStatus || $status->value === $row->status)) {
                $entries[] = $this->entry($shipmentId, $row);
            }
        }

        return new Page(\array_slice($entries, $page->offset(), $page->itemsPerPage), \count($entries), $page);
    }

    #[\Override]
    public function retry(TenantId $tenantId, string $shipmentId, \DateTimeImmutable $at): ?OutboxEntry
    {
        $row = $this->rows[$shipmentId] ?? null;
        if (null === $row || !$row->tenant->equals($tenantId) || 'failed' !== $row->status) {
            return null;
        }

        $row->status = 'pending';
        $row->attempts = 0;
        $row->next = $at;
        $row->error = null;

        return $this->entry($shipmentId, $row);
    }

    private function entry(string $shipmentId, OutboxRecord $row): OutboxEntry
    {
        return new OutboxEntry($shipmentId, $row->confirmation, OutboxStatus::from($row->status), $row->attempts, $row->next, $row->error, $row->next, null);
    }
}
