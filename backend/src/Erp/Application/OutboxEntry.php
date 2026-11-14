<?php

declare(strict_types=1);

namespace App\Erp\Application;

/**
 * A confirmation in the outbox, with what became of it, as the integration panel shows it.
 */
final readonly class OutboxEntry
{
    public function __construct(
        public string $shipmentId,
        public ShipmentConfirmation $confirmation,
        public OutboxStatus $status,
        public int $attempts,
        public \DateTimeImmutable $nextAttemptAt,
        public ?string $lastError,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $sentAt,
    ) {
    }
}
