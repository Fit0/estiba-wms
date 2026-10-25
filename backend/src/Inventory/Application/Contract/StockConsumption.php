<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

use App\Shared\Domain\TenantId;

/**
 * The goods of a reservation leave the warehouse: they were dispatched. Whatever the reservation
 * holds, wherever it waits, is issued from the stock.
 */
final readonly class StockConsumption
{
    public function __construct(
        public TenantId $tenantId,
        public string $reservationId,
        public string $reference,
        public string $authorId,
        public \DateTimeImmutable $at,
    ) {
    }
}
