<?php

declare(strict_types=1);

namespace App\Erp\Application;

/**
 * Units of a product that left with a shipment, by the SKU the ERP knows it with.
 */
final readonly class ShippedLine
{
    public function __construct(
        public string $sku,
        public int $units,
    ) {
    }
}
