<?php

declare(strict_types=1);

namespace App\Outbound\Application\Contract;

/**
 * Units of a product that left with a shipment.
 */
final readonly class ShippedUnits
{
    public function __construct(
        public string $productId,
        public int $units,
    ) {
    }
}
