<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

/**
 * Units of a product that exist but cannot be promised, for one reason. The codes of the locations
 * that hold them are there for a person to find them: a few, in order, and none when the reason is not
 * about a place.
 */
final readonly class UnreservableStock
{
    /**
     * @param list<string> $locationCodes
     */
    public function __construct(
        public UnreservableReason $reason,
        public int $units,
        public array $locationCodes,
    ) {
    }
}
