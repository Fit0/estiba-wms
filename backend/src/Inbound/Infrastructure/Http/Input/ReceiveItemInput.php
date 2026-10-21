<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\Input;

use App\Inbound\Domain\ItemCondition;

/**
 * A count at the dock: the scanned barcode and the base units counted.
 */
final readonly class ReceiveItemInput
{
    public function __construct(
        public string $gtin,
        public int $units,
        public ?string $lot = null,
        public ?\DateTimeImmutable $expiryDate = null,
        public ItemCondition $condition = ItemCondition::Good,
    ) {
    }
}
