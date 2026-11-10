<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Persistence;

/**
 * A row of the outbox of confirmations to the ERP. It only exists so the schema is described once, in the mapping:
 * {@see DbalShipmentOutbox} reads and writes the table with SQL, which is what lets it claim and move rows in single
 * statements. It is a persistence detail, so it lives here and not in the domain.
 */
final readonly class OutboxRow
{
    public function __construct(
        public string $shipmentId,
        public string $tenantId,
        public string $orderReference,
        public string $payload,
        public string $status,
        public int $attempts,
        public \DateTimeImmutable $nextAttemptAt,
        public ?string $lastError,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $sentAt,
    ) {
    }
}
