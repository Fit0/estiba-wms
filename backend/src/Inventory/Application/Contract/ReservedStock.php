<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

/**
 * Where the units of a reservation are, one stock item at a time: what a picker needs to go and
 * get them. It is a copy, taken when it was asked for.
 */
final readonly class ReservedStock
{
    public function __construct(
        public string $stockItemId,
        public string $locationId,
        public ?string $lot,
        public ?\DateTimeImmutable $expiryDate,
        public int $units,
    ) {
    }
}
