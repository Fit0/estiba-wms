<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

/**
 * What an allocation achieved. Covering less than was asked is not an error: the units that no
 * stock could give are the shortage, and a later allocation can cover them when stock arrives.
 */
final readonly class AllocationResult
{
    public function __construct(
        public string $reservationId,
        public int $requestedUnits,
        public int $allocatedUnits,
    ) {
    }

    public function shortageUnits(): int
    {
        return $this->requestedUnits - $this->allocatedUnits;
    }

    public function isComplete(): bool
    {
        return $this->allocatedUnits === $this->requestedUnits;
    }
}
