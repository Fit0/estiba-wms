<?php

declare(strict_types=1);

namespace App\Erp\Application;

use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;

/**
 * The confirmations of shipments that the ERP has still to be told about, and what became of the ones that were
 * sent (ADR 0049). A confirmation is `pending` until it is `sent`, or `failed` if it will not be tried again.
 */
interface ShipmentOutbox
{
    /**
     * Queues the confirmation of a shipment. A shipment that is already queued, in whatever state, is left alone, so
     * a second delivery of the same event queues nothing.
     */
    public function enqueue(TenantId $tenantId, string $shipmentId, ShipmentConfirmation $confirmation, \DateTimeImmutable $at): void;

    /**
     * The pending confirmations whose time has come, the ones that waited longest first.
     *
     * @return list<OutboxItem>
     */
    public function due(\DateTimeImmutable $now, int $limit): array;

    public function markSent(string $shipmentId, \DateTimeImmutable $at): void;

    /**
     * The ERP could not take it now: try again at `$nextAttemptAt`.
     */
    public function scheduleRetry(string $shipmentId, int $attempts, \DateTimeImmutable $nextAttemptAt, string $reason): void;

    /**
     * It will not be tried again, until a person decides to.
     */
    public function markFailed(string $shipmentId, int $attempts, string $reason): void;

    /**
     * The confirmations of the tenant, the latest first, optionally only the ones in a state.
     *
     * @return Page<OutboxEntry>
     */
    public function search(TenantId $tenantId, ?OutboxStatus $status, PageRequest $page): Page;

    /**
     * Puts a failed confirmation back to be sent, as if it had just been queued. Anything that is not failed, or that
     * is not of the tenant, is left alone.
     *
     * @return OutboxEntry|null the confirmation as it is now, or nothing if there was no failed one to put back
     */
    public function retry(TenantId $tenantId, string $shipmentId, \DateTimeImmutable $at): ?OutboxEntry;
}
