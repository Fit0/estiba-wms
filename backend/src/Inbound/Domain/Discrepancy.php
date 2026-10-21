<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

/**
 * A difference between what a receipt expected and what arrived, for one product. Discrepancies
 * are not stored: the receipt works them out from its expected items and its lines.
 */
final readonly class Discrepancy
{
    public function __construct(
        public DiscrepancyType $type,
        public ProductId $productId,
        public int $units,
    ) {
    }
}
