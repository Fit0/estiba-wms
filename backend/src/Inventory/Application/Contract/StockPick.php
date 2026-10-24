<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

use App\Shared\Domain\TenantId;

/**
 * A pick, as the floor reports it: from one of the stock items that cover a reservation, this many
 * units were taken to a place where they wait for packing, and this many could not be found.
 *
 * The units taken stay promised to the reservation. The ones not found are given up: they stop
 * being promised, and what the demand lacks is its shortage again.
 */
final readonly class StockPick
{
    public function __construct(
        public TenantId $tenantId,
        public string $reservationId,
        public string $stockItemId,
        public int $units,
        public int $shortUnits,
        public string $toLocationId,
        public string $reference,
        public string $authorId,
        public \DateTimeImmutable $at,
    ) {
    }
}
