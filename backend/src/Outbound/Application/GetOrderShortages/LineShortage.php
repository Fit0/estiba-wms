<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetOrderShortages;

use App\Inventory\Application\Contract\UnreservableStock;

/**
 * A line that is short, with the units that no stock covers and the stock that exists but cannot be used.
 * `obstacles` is empty when there is no stock of the product at all.
 */
final readonly class LineShortage
{
    /**
     * @param list<UnreservableStock> $obstacles
     */
    public function __construct(
        public int $lineNumber,
        public string $productId,
        public int $orderedUnits,
        public int $allocatedUnits,
        public int $shortageUnits,
        public array $obstacles,
    ) {
    }
}
