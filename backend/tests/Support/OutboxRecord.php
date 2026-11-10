<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Erp\Application\ShipmentConfirmation;
use App\Shared\Domain\TenantId;

/**
 * What {@see InMemoryShipmentOutbox} knows about one shipment.
 */
final class OutboxRecord
{
    public function __construct(
        public readonly TenantId $tenant,
        public readonly ShipmentConfirmation $confirmation,
        public string $status,
        public int $attempts,
        public \DateTimeImmutable $next,
        public ?string $error = null,
    ) {
    }
}
